<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\FromReflectionClass;
use Crell\AttributeUtils\HasSubAttributes;
use Crell\AttributeUtils\ParseProperties;

#[Attribute(Attribute::TARGET_CLASS)]
class Table implements FromReflectionClass, ParseProperties, HasSubAttributes
{
    /**
     * @var Field[]
     */
    public readonly array $fields;

    /**
     * @var class-string[]
     */
    public readonly array $triggers;

    public readonly string $className;

    public readonly ?string $name;

    public readonly ?PartitionByRange $partitionDef;

    public function __construct(
        ?string $name = null,
    ) {
        if ($name) {
            $this->name = $name;
        }
    }

    public function fromReflection(\ReflectionClass $subject): void
    {
        $this->name ??= $subject->getShortName();
        $this->className ??= $subject->getName();
    }

    public function setProperties(array $properties): void
    {
        $this->fields = $properties;
    }

    public function includePropertiesByDefault(): bool
    {
        return true;
    }

    public function propertyAttribute(): string
    {
        return Field::class;
    }

    public function subAttributes(): array
    {
        return [
            Trigger::class => 'fromTriggers',
            PartitionByRange::class => 'fromPartitionByRange',
        ];
    }

    /**
     * @param Trigger[] $triggers
     */
    public function fromTriggers(array $triggers): void
    {
        $list = [];
        foreach ($triggers as $trigger) {
            $list = [...$list, ...$trigger->triggers];
        }
        $this->triggers = $list;
    }

    public function fromPartitionByRange(?PartitionByRange $partitionDef): void
    {
        $this->partitionDef = $partitionDef;
    }
}
