<?php

namespace App\Service;

use App\Contract\PostServiceInterface;
use App\Contract\PostValidatorInterface;
use App\Contract\SerializerServiceInterface;
use App\Entity\Post;
use App\Entity\User;
use App\Exception\PostNotFoundException;
use App\Repository\PostRepository;
use Psr\Cache\InvalidArgumentException;

/**
 * Post Service
 */
readonly class PostService implements PostServiceInterface
{
    public function __construct(
        private PostRepository             $postRepository,
        private PostValidatorInterface     $postValidator,
        private SerializerServiceInterface $serializerService,
        private CacheService               $cacheService
    )
    {
    }

    /**
     * Get posts with pagination
     *
     * @param int $page
     * @param int $limit
     * @return array
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
     * @param int $id
     * @return Post
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
     * @param array|null $data
     * @param User $author
     * @return Post
     * @throws InvalidArgumentException
     */
    public function createPost(?array $data, User $author): Post
    {
        $this->postValidator->validatePostData($data);
        $post = $this->createPostFromData($data, $author);
        $this->postValidator->validatePost($post);
        $this->savePost($post);

        $this->cacheService->clearPostsListCache();

        return $post;
    }

    /**
     * Update existing post
     *
     * @param int $id
     * @param array|null $data
     * @param User $author
     * @return Post
     * @throws InvalidArgumentException
     * @throws PostNotFoundException
     */
    public function updatePost(int $id, ?array $data, User $author): Post
    {
        $post = $this->findPostById($id);
        $this->postValidator->checkPostOwnership($post, $author);
        $this->postValidator->validatePostData($data);

        $this->updatePostFromData($post, $data);
        $this->postValidator->validatePost($post);
        $this->savePost($post);

        $this->cacheService->clearPostDetailCache($id);
        $this->cacheService->clearPostsListCache();

        return $post;
    }

    /**
     * Delete a post
     *
     * @param int $id
     * @param User $user
     * @return void
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function deletePost(int $id, User $user): void
    {
        $post = $this->getPostById($id);
        $this->postValidator->checkPostOwnership($post, $user);

        $this->postRepository->remove($post, true);
        $this->cacheService->clearPostRelatedCache($id);
    }

    /**
     * Serialize post data for response
     *
     * @param Post $post
     * @param array $groups
     * @return array
     */
    public function serializePost(Post $post, array $groups = ['post:detail']): array
    {
        return $this->serializerService->serializePost($post, $groups);
    }

    /**
     * Create post entity from data
     */
    private function createPostFromData(array $data, User $author): Post
    {
        $post = new Post();
        $post->setAuthor($author);
        $post->setTitle($data['title']);
        $post->setContent($data['content']);

        return $post;
    }

    /**
     * Save post to database
     */
    private function savePost(Post $post): void
    {
        $this->postRepository->save($post, true);
    }

    /**
     * Find post by ID
     *
     * @throws PostNotFoundException
     */
    private function findPostById(int $id): Post
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            throw new PostNotFoundException('Post not found');
        }

        return $post;
    }

    /**
     * Update post with new data
     */
    private function updatePostFromData(Post $post, array $data): void
    {
        if (isset($data['title'])) {
            $post->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }
    }
}