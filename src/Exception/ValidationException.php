<?php

namespace App\Exception;

/**
 * Validation Exception
 */
class ValidationException extends BaseException
{
    public function __construct(
        string $message = '',
        private readonly array $validationErrors = []
    ) {
        parent::__construct($message);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getType(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}