<?php

namespace App\Contract;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;

/**
 * Serializer Service Interface
 */
interface SerializerServiceInterface
{
    /**
     * Serialize user data for response
     *
     * @param User $user
     * @param array $groups
     * @return array
     */
    public function serializeUser(User $user, array $groups = ['user:read']): array;

    /**
     * Serialize post data for response
     *
     * @param Post $post
     * @param array $groups
     * @return array
     */
    public function serializePost(Post $post, array $groups = ['post:read']): array;

    /**
     * Serialize comment data for response
     *
     * @param Comment $comment
     * @param array $groups
     * @return array
     */
    public function serializeComment(Comment $comment, array $groups = ['comment:read']): array;
}