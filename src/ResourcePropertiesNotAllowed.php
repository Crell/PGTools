<?php

declare(strict_types=1);

namespace Crell\PGTools;

class ResourcePropertiesNotAllowed extends \InvalidArgumentException
{
    public readonly string $name;

    public static function create(string $name): static
    {
        $new = new static();
        $new->name = $name;

        $new->message = sprintf('Resource properties cannot be persisted.  Please exclude %s.', $name);

        return $new;
    }
}
