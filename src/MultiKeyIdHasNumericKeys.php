<?php

declare(strict_types=1);

namespace Crell\PGTools;

class MultiKeyIdHasNumericKeys extends \InvalidArgumentException
{
    public readonly string $table;

    /**
     * @var array<string> $ids
     */
    public readonly array $ids;

    /**
     * @param array<string> $ids
     *
     * @todo Not sure if the type is right here, honestly...
     */
    public static function create(string $table, array $ids): self
    {
        $new = new self();
        $new->table = $table;
        $new->ids = $ids;

        $new->message = 'A multi-key ID must include a name for each field.';

        return $new;
    }
}
