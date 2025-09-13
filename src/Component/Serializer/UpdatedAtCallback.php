<?php

declare(strict_types=1);

namespace App\Component\Serializer;

use DateTimeInterface;

/**
 * Updated At Callback for Symfony Serializer
 */
class UpdatedAtCallback
{
    /**
     * @param string|DateTimeInterface|null $innerObject
     * @return string|null
     */
    public function __invoke(null|string|DateTimeInterface $innerObject): string|null
    {
        if ($innerObject === null) {
            return null;
        }

        if ($innerObject instanceof DateTimeInterface) {
//            return $innerObject;
            return $innerObject->format('c');
        }

//        return $innerObject->format('Y-m-d H:i:s');
        return $innerObject;
    }
}