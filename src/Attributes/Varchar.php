<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Varchar implements ColumnType
{
    public function __construct(private int $chars = 255) {}

    public function pgType(): string
    {
        return "varchar($this->chars)";
    }
}
