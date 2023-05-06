<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ClassSource
{
    public function __construct(
        public readonly string $column,
    ) {}
}
