<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\Serde\TypeField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class SequenceField implements ColumnType
{
    /**
     * @param string $arrayType
     *   Elements in this array are objects of this type.
     */
    public function __construct(
        public readonly string $arrayType,
    ) {}

    public function pgType(): string
    {
        // Per https://www.postgresql.org/docs/15/arrays.html
        // Any depth or size of array specified has no effect and is ignored,
        // so we don't need to bother with it.
        return $this->arrayType . '[]';
    }

}
