<?php

declare(strict_types=1);

namespace Crell\PGTools;

interface RawFunction
{
    public function completeFunction(): string;
}
