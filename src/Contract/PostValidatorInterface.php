<?php

namespace App\Contract;

use App\Entity\Post;
use App\Entity\User;

/**
 * Post Validator Interface
 */
interface PostValidatorInterface
{
    /**
     * Check if user can modify post
     *
     * @param Post $post
     * @param User $user
     * @return void
     */
    public function checkPostOwnership(Post $post, User $user): void;

    /**
     * Validate post entity
     *
     * @param Post $post
     * @return void
     */
    public function validatePost(Post $post): void;

    /**
     * Validate post data
     *
     * @param array|null $data
     * @return void
     */
    public function validatePostData(?array $data): void;
}