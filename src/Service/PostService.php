<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use App\Repository\PostRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class PostService
{
    public function __construct(
        private PostRepository      $postRepository,
        private ValidatorInterface  $validator,
        private SerializerInterface $serializer,
        private CacheService        $cacheService
    )
    {
    }

    /**
     * Validate post data format and required fields
     *
     * @throws InvalidInputException
     */
    public function validatePostData(?array $data): void
    {
        if (!$data) {
            throw new InvalidInputException('Invalid JSON body');
        }

        $requiredFields = ['title', 'content'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new InvalidInputException("Field {$field} is required");
            }
        }
    }

    /**
     * Create post entity from data
     */
    public function createPostFromData(array $data, User $author): Post
    {
        $post = new Post();
        $post->setAuthor($author);
        $post->setTitle($data['title']);
        $post->setContent($data['content']);

        return $post;
    }

    /**
     * Validate post entity using Symfony validator
     *
     * @throws ValidationException
     */
    public function validatePost(Post $post): void
    {
        $errors = $this->validator->validate($post);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new ValidationException('Validation failed', $errorMessages);
        }
    }

    /**
     * Save post to database
     */
    public function savePost(Post $post): void
    {
        $this->postRepository->save($post, true);
    }

    /**
     * Find post by ID
     *
     * @throws PostNotFoundException
     */
    public function findPostById(int $id): Post
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            throw new PostNotFoundException('Post not found');
        }

        return $post;
    }

    /**
     * Check if user can modify post
     *
     * @throws AccessDeniedException
     */
    public function checkPostOwnership(Post $post, User $user): void
    {
        if ($post->getAuthor() !== $user) {
            throw new AccessDeniedException('Access denied. You can only modify your own posts');
        }
    }

    /**
     * Update post with new data
     */
    public function updatePostFromData(Post $post, array $data): void
    {
        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
    }

    /**
     * Delete a post
     *
     * @throws PostNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    public function deletePost(int $id, User $user): void
    {
        $post = $this->getPostById($id);
        $this->checkPostOwnership($post, $user);

        $this->postRepository->remove($post, true);
        $this->cacheService->clearPostRelatedCache($id);
    }

    /**
     * Serialize post data for response
     */
    public function serializePost(Post $post, array $groups = ['post:read']): array
    {
        $postData = $this->serializer->serialize($post, 'json', ['groups' => $groups]);

        return json_decode($postData, true);
    }

    /**
     * Get posts with pagination
     *
     * @throws InvalidArgumentException
     */
    public function getPostsWithPagination(int $page, int $limit): array
    {
        $cacheKey = $this->cacheService->getPostsListCacheKey($page, $limit);

        return $this->cacheService->getCache()->get($cacheKey, function ($item) use ($page, $limit) {
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
    }

    /**
     * Get single post
     *
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function getPostById(int $id): Post
    {
        $cacheKey = $this->cacheService->getPostDetailCacheKey($id);

        $post = $this->cacheService->getCache()->get($cacheKey, function ($item) use ($id) {
            $item->expiresAfter($this->cacheService->getListCacheTime());

            return $this->postRepository->find($id);
        });

        if (!$post) {
            throw new PostNotFoundException('Post not found');
        }

        return $post;
    }

    /**
     * Create new post
     *
     * @throws ValidationException
     * @throws InvalidInputException
     * @throws InvalidArgumentException
     */
    public function createPost(?array $data, User $author): Post
    {
        $this->validatePostData($data);
        $post = $this->createPostFromData($data, $author);
        $this->validatePost($post);
        $this->savePost($post);

        $this->cacheService->clearPostsListCache();

        return $post;
    }

    /**
     * Update existing post
     *
     * @throws InvalidInputException
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     * @throws ValidationException
     * @throws PostNotFoundException
     */
    public function updatePost(int $id, ?array $data, User $author): Post
    {
        $post = $this->findPostById($id);
        $this->checkPostOwnership($post, $author);
        $this->validatePostData($data);

        $this->updatePostFromData($post, $data);
        $this->validatePost($post);
        $this->savePost($post);

        $this->cacheService->clearPostDetailCache($id);
        $this->cacheService->clearPostsListCache();

        return $post;
    }
}