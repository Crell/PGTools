<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

interface DeserializesToObject extends ColumnType
{
    /**
     * Returns the class name to deserialize this field to, using the raw record.
     *
     * @param array<mixed> $record
     *   The record from which to extract the class name.
     * @return string
     */
    public function className(array $record): string;
}
