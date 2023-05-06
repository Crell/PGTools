<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

interface DeserializesToObject
{
    public function className(array $record): string;
}
