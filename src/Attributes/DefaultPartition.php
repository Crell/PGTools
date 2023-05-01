<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class DefaultPartition
{
    public function __construct(
        public readonly string $name,
    ) {}
}
