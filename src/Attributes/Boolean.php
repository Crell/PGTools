<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Boolean implements ColumnType
{
    public function pgType(): string
    {
        return 'boolean';
    }
}
