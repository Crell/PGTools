<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Attributes\Field;

class ResultTypeColumnMissing extends \RuntimeException
{
    public readonly Field $field;
    public readonly string $resultClass;

    /**
     * @param class-string $resultClass
     */
    public static function create(Field $field, string $resultClass): self
    {
        $new = new self();
        $new->field = $field;
        $new->resultClass = $resultClass;

        $new->message = sprintf('Fetching into result class %s expected a column %s to match to field %s, but none was found', $resultClass, $field->column, $field->name);

        return $new;
    }
}
