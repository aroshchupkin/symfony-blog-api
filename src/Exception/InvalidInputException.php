<?php

namespace App\Exception;

/**
 * Invalid Input Exception
 */
class InvalidInputException extends UserException
{
    public function getHttpStatusCode(): int
    {
        return 400;
    }
}