<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Service\CacheService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/posts')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository      $postRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface  $validator,
        private readonly CacheService        $cacheService
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'posts_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, max(1, (int)$request->query->get('limit', 10)));

        $cacheKey = $this->cacheService->getPostsListCacheKey($page, $limit);

        $result = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($page, $limit) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            $posts = $this->postRepository->findAllWithPagination($page, $limit);
            $total = $this->postRepository->countAll();
            $totalPages = (int)ceil($total / $limit);

            return [
                'posts' => $posts,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_previous_page' => $page > 1,
                ]
            ];
        });

        $data = $this->serializer->serialize($result, 'json', ['groups' => ['post:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $cacheKey = $this->cacheService->getPostDetailCacheKey($id);

        $post = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->postRepository->find($id);
        });

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'posts_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = new Post();
        $post->setAuthor($user);

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }

        $errors = $this->validator->validate($post);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->postRepository->save($post, true);

        $this->cacheService->clearPostsListCache();

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($post->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only update your own posts',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }

        $errors = $this->validator->validate($post);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'error' => 'Validation failed',
                'code' => 'VALIDATION_ERROR',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->postRepository->save($post, true);

        $this->cacheService->clearPostDetailCache($id);
        $this->cacheService->clearPostsListCache();

        $data = $this->serializer->serialize($post, 'json', ['groups' => ['post:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'posts_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $post = $this->postRepository->find($id);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($post->getAuthor() !== $user) {
            return new JsonResponse([
                'error' => 'Access denied. You can only delete your own posts',
                'code' => 'ACCESS_DENIED'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->postRepository->remove($post, true);

        $this->cacheService->clearPostRelatedCache($id);

        return new JsonResponse([
            'message' => 'Post deleted successfully',
            'code' => 'POST_DELETED'
        ], Response::HTTP_OK);
    }
}
