<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TimestampWithTimezone implements ColumnType
{
    public function pgType(): string
    {
        return 'timestamp with time zone';
    }
}
