<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\HasSubAttributes;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonB implements ColumnType, DeserializesToObject, HasSubAttributes
{
    /**
     * @var class-string
     */
    private readonly ?string $classColumn;

    public function pgType(): string
    {
        return 'jsonb';
    }

    public function subAttributes(): array
    {
        return [
            ClassSource::class => 'fromClassSource',
        ];
    }

    public function fromClassSource(?ClassSource $source): void
    {
        $this->classColumn = $source?->column;
    }

    public function className(array $record): string
    {
        return $record[$this->classColumn];
    }

}
