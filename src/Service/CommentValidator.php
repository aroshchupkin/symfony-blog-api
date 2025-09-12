<?php

namespace App\Service;

use App\Contract\CommentValidatorInterface;
use App\Entity\Comment;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\InvalidInputException;
use App\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Comment Validator
 */
readonly class CommentValidator implements CommentValidatorInterface
{
    /**
     * Constructor
     *
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws InvalidInputException
     */
    public function validateCommentData(?array $data): void
    {
        if (!$data) {
            throw new InvalidInputException('Invalid JSON body');
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            throw new InvalidInputException('Field content is required');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateComment(Comment $comment): void
    {
        $errors = $this->validator->validate($comment);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            throw new ValidationException('Validation failed', $errorMessages);
        }
    }

    /**
     * @throws AccessDeniedException
     */
    public function checkCommentOwnership(Comment $comment, User $user): void
    {
        if ($comment->getAuthor() !== $user) {
            throw new AccessDeniedException('Access denied. You can only modify your own comments');
        }
    }
}