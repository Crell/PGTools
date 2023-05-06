<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\FromReflectionClass;
use Crell\AttributeUtils\HasSubAttributes;
use Crell\AttributeUtils\ParseProperties;

#[Attribute(Attribute::TARGET_CLASS)]
class Result implements FromReflectionClass, ParseProperties
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
}
