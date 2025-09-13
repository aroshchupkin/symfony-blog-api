<?php

declare(strict_types=1);

namespace App\Component\Serializer;

/**
 * Circular Reference Handler for Symfony Serializer
 */
class CircularReferenceHandler
{
    /**
     * @param $object
     * @return mixed
     */
    public function __invoke($object)
    {
        return $object->getId();
    }
}