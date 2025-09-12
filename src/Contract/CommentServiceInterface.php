<?php

namespace App\Contract;

use App\Entity\Comment;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\CommentNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\PostNotFoundException;
use App\Exception\ValidationException;
use Psr\Cache\InvalidArgumentException;

/**
 * Comment Service Interface
 */
interface CommentServiceInterface
{
    /**
     * Get comments for a specific post with pagination
     *
     * @param int $postId
     * @param int $page
     * @param int $limit
     * @return array
     * @throws PostNotFoundException
     * @throws InvalidArgumentException
     */
    public function getCommentsForPost(int $postId, int $page, int $limit): array;

    /**
     * Get a single comment by ID
     *
     * @param int $id
     * @return Comment
     * @throws CommentNotFoundException
     * @throws InvalidArgumentException
     */
    public function getCommentById(int $id): Comment;

    /**
     * Create a new comment
     *
     * @param array|null $data
     * @param int $postId
     * @param User $user
     * @return Comment
     * @throws InvalidInputException
     * @throws PostNotFoundException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function createComment(?array $data, int $postId, User $user): Comment;

    /**
     * Update an existing comment
     *
     * @param int $id
     * @param array|null $data
     * @param User $user
     * @return Comment
     * @throws InvalidInputException
     * @throws CommentNotFoundException
     * @throws AccessDeniedException
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function updateComment(int $id, ?array $data, User $user): Comment;

    /**
     * Delete a comment
     *
     * @param int $id
     * @param User $user
     * @return void
     * @throws CommentNotFoundException
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     */
    public function deleteComment(int $id, User $user): void;

    /**
     * Serialize comment data
     *
     * @param Comment $comment
     * @return array
     */
    public function serializeComment(Comment $comment): array;
}