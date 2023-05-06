<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\Multivalue;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class PartitionList implements Multivalue
{
    /**
     * @param string $name
     * @param array<int|string|float|bool> $values
     */
    public function __construct(
        public readonly string $name,
        public readonly array $values,
    ) {}
}
