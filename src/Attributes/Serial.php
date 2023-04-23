<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Serial implements ColumnType
{
    public function pgType(): string
    {
        return 'serial';
    }
}
