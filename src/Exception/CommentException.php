<?php

namespace App\Exception;

use Exception;

class CommentException extends \Exception
{
    public function __construct(
        string $message = "Comment error occurred",
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}