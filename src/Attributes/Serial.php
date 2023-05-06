<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

/**
 * Serial is not a recommended type in PgSql anymore, as it's non-standard SQL.
 *
 * It's very convenient, though, so we're going to use it as a syntax to
 * generate the correct standard SQL.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Serial implements ColumnType
{
    public function pgType(): string
    {
        return 'integer generated always as identity';
    }
}
