<?php

namespace App\Exception;

/**
 * Post Exception
 */
class PostException extends BaseException
{
    public function getType(): string
    {
        return 'POST_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}