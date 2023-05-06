<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\Multivalue;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class Trigger implements Multivalue
{
    /**
     * @var string[]
     */
    public readonly array $triggers;

    public function __construct(
        string ...$triggers,
    ) {
        $this->triggers = $triggers;
    }
}
