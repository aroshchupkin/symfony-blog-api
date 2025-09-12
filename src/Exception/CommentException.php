<?php

namespace App\Exception;

/**
 * Comment Exception
 */
class CommentException extends BaseException
{
    public function getType(): string
    {
        return 'COMMENT_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}