<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonB implements ColumnType, DeserializesToObject
{
    public function __construct(
        public readonly ?string $classColumn = null,
    ) {}

    public function pgType(): string
    {
        return 'jsonb';
    }

    public function className(array $record): string
    {
        return $record[$this->classColumn];
    }
}
