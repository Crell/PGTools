<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\HasSubAttributes;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class PartitionByList implements HasSubAttributes, PartitionType
{
    /**
     * @var string[]
     */
    public readonly array $columns;

    /**
     * @var PartitionList[]
     */
    public readonly array $partitions;

    public readonly ?DefaultPartition $defaultPartition;

    public function __construct(
        public readonly string $column,
    ) {
    }

    public function subAttributes(): array
    {
        return [
            PartitionList::class => 'fromPartitionList',
            DefaultPartition::class => 'fromDefaultPartition',
        ];
    }

    /**
     * @param PartitionList[] $partitions
     */
    public function fromPartitionList(array $partitions = []): void
    {
        $this->partitions = $partitions;
    }

    public function fromDefaultPartition(?DefaultPartition $defaultPartition = null): void
    {
        $this->defaultPartition = $defaultPartition;
    }
}
