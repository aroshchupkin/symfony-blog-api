<?php

namespace App\Exception;

/**
 * Email Already Exists Exception
 */
class EmailAlreadyExistsException extends UserException
{
    public function getHttpStatusCode(): int
    {
        return 409;
    }
}