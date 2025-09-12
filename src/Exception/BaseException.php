<?php

namespace App\Exception;

/**
 * Base Exception Class
 */
abstract class BaseException extends \Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get exception type
     */
    abstract public function getType(): string;

    /**
     * Get HTTP status code
     */
    abstract public function getHttpStatusCode(): int;
}