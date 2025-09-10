<?php

namespace App\Exception;

class ValidationException extends UserException
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
}