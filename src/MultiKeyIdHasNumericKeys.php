<?php

declare(strict_types=1);

namespace Crell\PGTools;

class MultiKeyIdHasNumericKeys extends \InvalidArgumentException
{
    public readonly string $table;
    public readonly array $ids;

    public static function create(string $table, array $ids): static
    {
        $new = new static();
        $new->table = $table;
        $new->ids = $ids;

        $new->message = 'A multi-key ID must include a name for each field.';

        return $new;
    }
}
