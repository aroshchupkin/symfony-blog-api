<?php

namespace App\Exception;

class CommentNotFoundException extends CommentException
{
    public function __construct(string $message = "Comment not found")
    {
        parent::__construct($message);
    }
}