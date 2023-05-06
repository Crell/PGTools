<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TimestampWithTimezone implements ColumnType, SelfDecodingColumn
{
    public function pgType(): string
    {
        return 'timestamp with time zone';
    }

    /**
     * @param string $value
     * @return \DateTimeImmutable
     */
    public function decode(bool|int|string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }
}
