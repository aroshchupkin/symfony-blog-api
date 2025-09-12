<?php

namespace App\Exception;

/**
 * User Exception
 */
class UserException extends BaseException
{
    public function getType(): string
    {
        return 'USER_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}