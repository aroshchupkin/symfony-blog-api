<?php

namespace App\Exception;

/**
 * Access Denied Exception
 */
class AccessDeniedException extends BaseException
{
    public function getType(): string
    {
        return 'ACCESS_DENIED';
    }

    public function getHttpStatusCode(): int
    {
        return 403;
    }
}