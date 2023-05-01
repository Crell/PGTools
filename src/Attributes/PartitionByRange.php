<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\HasSubAttributes;

#[Attribute(Attribute::TARGET_CLASS|Attribute::IS_REPEATABLE)]
class PartitionByRange implements HasSubAttributes
{
    /**
     * @var string[]
     */
    public readonly array $columns;

    /**
     * @var PartitionRange[]
     */
    public readonly array $partitions;

    public readonly ?DefaultPartition $defaultPartition;

    public function __construct(
        string ...$columns,
    ) {
        $this->columns = $columns;
    }

    public function subAttributes(): array
    {
        return [
            PartitionRange::class => 'fromPartition',
            DefaultPartition::class => 'fromDefaultPartition',
        ];
    }

    public function fromPartition(array $partitions = []): void
    {
        $this->partitions = $partitions;
    }

    public function fromDefaultPartition(?DefaultPartition $defaultPartition = null): void
    {
        $this->defaultPartition = $defaultPartition;
    }
}
