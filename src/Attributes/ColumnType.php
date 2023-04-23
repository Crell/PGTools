<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

interface ColumnType
{
    public function pgType(): string;
}
