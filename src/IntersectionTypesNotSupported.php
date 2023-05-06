<?php

declare(strict_types=1);

namespace Crell\PGTools;

class IntersectionTypesNotSupported extends \TypeError
{
    // @todo Use this in the error message.
    public readonly \ReflectionProperty $property;

    public static function create(\ReflectionProperty $property): self
    {
        $new = new self();
        $new->property = $property;
        return $new;
    }
}
