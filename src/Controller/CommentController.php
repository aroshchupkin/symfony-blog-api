<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Repository\CommentRepository;
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

#[Route('/api/posts/{postId}/comments')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository   $commentRepository,
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
    #[Route('', name: 'comments_index', requirements: ['postId' => '\d+'], methods: ['GET'])]
    public function index(int $postId, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, max(1, (int)$request->query->get('limit', 10)));

        $cacheKey = $this->cacheService->getCommentsListCacheKey($postId, $page, $limit);

        $result = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($post, $page, $limit) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            $comments = $this->commentRepository->findByPostWithPagination($post, $page, $limit);
            $total = $this->commentRepository->countByPost($post);
            $totalPages = (int)ceil($total / $limit);

            return [
                'comments' => $comments,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'items_per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_prev_page' => $page > 1
                ]
            ];
        });

        $data = $this->serializer->serialize($result, 'json', ['groups' => ['comment:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_show', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['GET'])]
    public function show(int $postId, int $id): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $cacheKey = $this->cacheService->getCommentDetailCacheKey($id);

        $comment = $this->cacheService->getCache()->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->commentRepository->find($id);
        });

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:list']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'comments_create', requirements: ['postId' => '\d+'], methods: ['POST'])]
    public function create(int $postId, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $comment = new Comment();
        $comment->setPost($post);

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        if (isset($data['authorName'])) {
            $comment->setAuthorName($data['authorName']);
        }

        if (isset($data['authorEmail'])) {
            $comment->setAuthorEmail($data['authorEmail']);
        }

        $errors = $this->validator->validate($comment);

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

        $this->commentRepository->save($comment, true);

        $this->cacheService->clearCommentsListCache($postId);

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:read']]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_update', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['PATCH'])]
    public function update(int $postId, int $id, Request $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $comment = $this->commentRepository->find($id);

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
                'code' => 'INVALID_JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        if (isset($data['authorName'])) {
            $comment->setAuthorName($data['authorName']);
        }

        if (isset($data['authorEmail'])) {
            $comment->setAuthorEmail($data['authorEmail']);
        }

        $errors = $this->validator->validate($comment);

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

        $this->commentRepository->save($comment, true);

        $this->cacheService->clearCommentRelatedCache($id, $postId);

        $data = $this->serializer->serialize($comment, 'json', ['groups' => ['comment:read']]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/{id}', name: 'comments_delete', requirements: ['postId' => '\d+', 'id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $postId, int $id): JsonResponse
    {
        $post = $this->postRepository->find($postId);

        if (!$post) {
            return new JsonResponse([
                'error' => 'Post not found',
                'code' => 'POST_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $comment = $this->commentRepository->find($id);

        if (!$comment || $comment->getPost()->getId() !== $post->getId()) {
            return new JsonResponse([
                'error' => 'Comment not found',
                'code' => 'COMMENT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->commentRepository->remove($comment, true);

        $this->cacheService->clearCommentRelatedCache($id, $postId);

        return new JsonResponse([
            'message' => 'Comment deleted successfully',
            'code' => 'COMMENT_DELETED'
        ], Response::HTTP_OK);
    }
}
