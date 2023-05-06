<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

interface SelfDecodingColumn extends ColumnType
{
    /**
     * Translates a value from a DB column to PHP type.
     *
     * @param int|string|bool $value
     * @return mixed
     */
    public function decode(int|string|bool $value): mixed;
}
