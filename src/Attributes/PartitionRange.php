<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\Multivalue;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class PartitionRange implements Multivalue
{
    public function __construct(
        public readonly string $name,
        public readonly array $from,
        public readonly array $to,
    ) {}
}
