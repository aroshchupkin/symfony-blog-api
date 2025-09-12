<?php

namespace App\Service;

use App\Contract\PostValidatorInterface;
use App\Entity\Post;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Post Validator
 */
readonly class PostValidator implements PostValidatorInterface
{
    /**
     * Constructor
     *
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private ValidatorInterface $validator,
    )
    {
    }

    /**
     * Check if user can modify post
     *
     * @param Post $post
     * @param User $user
     * @return void
     * @throws AccessDeniedException
     */
    public function checkPostOwnership(Post $post, User $user): void
    {
        if ($post->getAuthor() !== $user) {
            throw new AccessDeniedException('Access denied. You can only modify your own posts');
        }
    }

    /**
     * Validate post entity
     *
     * @param Post $post
     * @return void
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
     * Validate post data
     *
     * @param array|null $data
     * @return void
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
}