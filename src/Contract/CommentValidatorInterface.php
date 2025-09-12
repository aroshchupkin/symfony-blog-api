<?php

namespace App\Contract;

use App\Entity\Comment;
use App\Entity\User;

/**
 * Comment Validator Interface
 */
interface CommentValidatorInterface
{
    /**
     * Validate comment data
     *
     * @param array|null $data
     * @return void
     */
    public function validateCommentData(?array $data): void;

    /**
     * Validate comment entity
     *
     * @param Comment $comment
     * @return void
     */
    public function validateComment(Comment $comment): void;

    /**
     * Check if user owns the comment
     *
     * @param Comment $comment
     * @param User $user
     * @return void
     */
    public function checkCommentOwnership(Comment $comment, User $user): void;
}