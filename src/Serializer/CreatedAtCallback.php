<?php

declare(strict_types=1);

namespace App\Serializer;

use DateTimeInterface;

class CreatedAtCallback
{
    public function __invoke(null|string|DateTimeInterface $innerObject): DateTimeInterface|string|null
    {
        if ($innerObject === null) {
            return null;
        }

        if ($innerObject instanceof DateTimeInterface) {
            return $innerObject;
        }

        return $innerObject->format('Y-m-d H:i:s');
    }
}