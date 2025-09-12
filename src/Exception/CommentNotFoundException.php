<?php

namespace App\Exception;

/**
 * Comment Not Found Exception
 */
class CommentNotFoundException extends CommentException
{
    public function __construct(string $message = "Comment not found")
    {
        parent::__construct($message);
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}