<?php

namespace App\Exception;

/**
 * Post Not Found Exception
 */
class PostNotFoundException extends PostException
{
    public function getHttpStatusCode(): int
    {
        return 404;
    }
}