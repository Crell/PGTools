<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Attributes\Field;

class ResultTypeColumnMissing extends \RuntimeException
{
    public readonly Field $field;
    public readonly string $resultClass;

    /**
     * @param Field $field
     * @param class-string $resultClass
     * @return static
     */
    public static function create(Field $field, string $resultClass): static
    {
        $new = new static();
        $new->field = $field;
        $new->resultClass = $resultClass;

        $new->message = sprintf('Fetching into result class %s expected a column %s to match to field %s, but none was found', $resultClass, $field->column, $field->name);

        return $new;
    }
}
