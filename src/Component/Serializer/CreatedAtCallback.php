<?php

declare(strict_types=1);

namespace App\Component\Serializer;

use DateTimeInterface;

/**
 * Create At Callback for Symfony Serializer
 */
class CreatedAtCallback
{
    /**
     * @param string|DateTimeInterface|null $innerObject
     * @return DateTimeInterface|string|null
     */
    public function __invoke(null|string|DateTimeInterface $innerObject): DateTimeInterface|string|null
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