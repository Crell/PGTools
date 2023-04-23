<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\FromReflectionClass;
use Crell\AttributeUtils\ParseProperties;

#[Attribute(Attribute::TARGET_CLASS)]
class Table implements FromReflectionClass, ParseProperties
{
    /**
     * @var Field[]
     */
    public readonly array $fields;

    public readonly string $className;

    public readonly ?string $name;

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

    /**
     * @todo tri-state logic? Eew. Maybe need separate methods, or an enum, or something.
     * @return Field[]
     */
    public function getIdFields(?bool $generated = null): array
    {
        return match($generated) {
            true => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId && $field->isGeneratedId)),
            false => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId && !$field->isGeneratedId)),
            null => array_values(array_filter($this->fields, static fn(Field $field): bool => $field->isId)),
        };
    }

    /**
     * @return Field[]
     */
    public function getValueFields(): array
    {
        return array_values(array_filter($this->fields, static fn(Field $field): bool => !$field->isId));
    }
}
