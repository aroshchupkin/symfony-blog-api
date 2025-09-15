<?php

namespace App\Contract;

use App\Entity\Post;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use Psr\Cache\InvalidArgumentException;

/**
 * Post Service Interface
 */
interface PostServiceInterface
{
    /**
     * Get posts with pagination
     *
     * @param int $page
     * @param int $limit
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPostsWithPagination(int $page, int $limit): array;

    /**
     * Get single post
     *
     * @param int $id
     * @return Post
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function getPostById(int $id): Post;

    /**
     * Create new post
     *
     * @param array|null $data
     * @param User $author
     * @return Post
     * @throws InvalidInputException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function createPost(?array $data, User $author): Post;

    /**
     * Update existing post
     *
     * @param int $id
     * @param array|null $data
     * @param User $author
     * @return Post
     * @throws PostNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidInputException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function updatePost(int $id, ?array $data, User $author): Post;

    /**
     * Delete a post
     *
     * @param int $id
     * @param User $user
     * @return void
     * @throws PostNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    public function deletePost(int $id, User $user): void;

    /**
     * Serialize post data for response
     *
     * @param Post $post
     * @param array $groups
     * @return array
     */
    public function serializePost(Post $post, array $groups = ['post:detail']): array;
}