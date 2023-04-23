<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;
use Crell\AttributeUtils\Excludable;
use Crell\AttributeUtils\FromReflectionProperty;
use Crell\AttributeUtils\HasSubAttributes;
use Crell\PGTools\DefaultValue;
use Crell\PGTools\IntersectionTypesNotSupported;
use Crell\PGTools\ResourcePropertiesNotAllowed;
use Crell\PGTools\UnionTypesNotSupported;
use function Crell\fp\indexBy;
use function Crell\fp\method;
use function Crell\fp\pipe;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field implements FromReflectionProperty, HasSubAttributes, Excludable
{
    /* readonly */ public bool $isId;

    /* readonly */ public bool $isGeneratedId;

    /**
     * The native PHP type, as the reflection system defines it.
     */
    public readonly string $phpType;

    /**
     * The name of the property in PHP. ($column is the name of the DB field.)
     */
    public readonly string $name;

    /**
     * The name of the property's corresponding column in the DB.
     */
    public readonly ?string $column;

    public readonly DefaultValue $default;

    public readonly ColumnType $columnType;

    public readonly bool $isNullable;

    public function __construct(
        // $field is the name of the field in the DB.
        ?string $column = null,
        private readonly bool $exclude = false,
        mixed $default = null,
        public readonly ?int $length = null,
        public readonly ?bool $unsigned = null,
    ) {
        if ($column) {
            $this->column = $column;
        }
        if ($default) {
            $this->default = DefaultValue::Value($default);
        }
    }

    public function exclude(): bool
    {
        return $this->exclude;
    }

    public function fromReflection(\ReflectionProperty $subject): void
    {
        $this->name = $subject->name;
        $this->phpType ??= $this->getNativeType($subject);
        $this->doctrineType ??= $this->getDoctrineType($this->phpType);
        $this->column ??= $subject->name;
        $this->default ??= $this->getDefaultValueFromConstructor($subject);
        // If there's no type defined, that means it's mixed, which includes null.
        $this->isNullable ??= $subject->getType()?->allowsNull() ?? true;
    }

    public function subAttributes(): array
    {
        return [
            Id::class => 'fromId',
            ColumnType::class => 'fromColumnType',
        ];
    }

    public function fromId(?Id $id = null): void
    {
        $this->isId = isset($id);
        $this->isGeneratedId = $id?->generate ?? false;

        // This sorta hard-codes integer IDs.  UUID will require changes here.
        if ($this->isGeneratedId) {
            $this->columnType = new Serial();
        }
    }

    public function fromColumnType(?ColumnType $type = null): void
    {
        $this->columnType ??= $type ?? $this->deriveType($this->phpType);
    }

    private function deriveType(string $phpType): ColumnType
    {
        return match ($phpType) {
            'int' => new Integer(),
            'string' => new Varchar(),
//            'float' => 'float',
            // Only ever allow storing datetime with TZ data.
            \DateTime::class => new TimestampWithTimezone(),
            \DateTimeImmutable::class => 'datetimetz_immutable',
//            'array' => 'json',
            // Probably a bad default.
            default => new Text(),
        };
    }

    /**
     * Maps to the Doctrine Schema addField(options) array. Check the docs there.
     */
    public function options(): array
    {
        $ret = [];
        foreach (['default', 'length', 'unsigned'] as $key) {
            if ($this->$key) {
                $ret[$key] = $this->$key;
            }
        }
        if ($this->isGeneratedId) {
            $ret['autoincrement'] = true;
        }
        return $ret;
    }

    protected function getNativeType(\ReflectionProperty $property): string
    {
        $rType = $property->getType();
        return match(true) {
            $rType instanceof \ReflectionUnionType => throw UnionTypesNotSupported::create($property),
            $rType instanceof \ReflectionIntersectionType => throw IntersectionTypesNotSupported::create($property),
            $rType instanceof \ReflectionNamedType => $rType->getName(),
        };
    }

    protected function getDefaultValueFromConstructor(\ReflectionProperty $subject): DefaultValue
    {
        /** @var array<string, \ReflectionParameter> $params */
        $params = pipe($subject->getDeclaringClass()->getConstructor()?->getParameters() ?? [],
            indexBy(method('getName')),
        );

        $param = $params[$subject->getName()] ?? null;

        return $param?->isDefaultValueAvailable()
            ? DefaultValue::Value($param->getDefaultValue())
            : DefaultValue::NoValue();
    }

    protected function getDoctrineType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'string' => 'string',
            'float' => 'float',
            // Only ever allow storing datetime with TZ data.
            \DateTime::class => 'datetimetz',
            \DateTimeImmutable::class => 'datetimetz_immutable',
            'array' => 'json',
            // @todo Need a test case for this.
            'resource' => throw ResourcePropertiesNotAllowed::create('Fix this string'),
            default => 'json',
        };
    }
}
