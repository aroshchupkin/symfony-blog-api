<?php

namespace App\Service;

use App\Contract\SerializerServiceInterface;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Serializer Service
 */
readonly class SerializerService implements SerializerServiceInterface
{
    /**
     * Constructor
     *
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * Serialize User entity to array
     *
     * @param User $user
     * @param array $groups
     * @return array
     */
    public function serializeUser(User $user, array $groups = ['user:read']): array
    {
        $userData = $this->serializer->serialize($user, 'json', ['groups' => $groups]);
        return json_decode($userData, true);
    }

    /**
     * Serialize Post entity to array
     *
     * @param Post $post
     * @param array $groups
     * @return array
     */
    public function serializePost(Post $post, array $groups = ['post:read']): array
    {
        $postData = $this->serializer->serialize($post, 'json', ['groups' => $groups]);
        return json_decode($postData, true);
    }

    /**
     * Serialize Comment entity to array
     *
     * @param Comment $comment
     * @param array $groups
     * @return array
     */
    public function serializeComment(Comment $comment, array $groups = ['comment:read']): array
    {
        $commentData = $this->serializer->serialize($comment, 'json', ['groups' => $groups]);
        return json_decode($commentData, true);
    }
}