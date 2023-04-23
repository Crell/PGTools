<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TimestampWithTimezoneImmutable implements ColumnType
{
    public function pgType(): string
    {
        return 'timestamp with time zone';
    }
}
