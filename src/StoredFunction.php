<?php

declare(strict_types=1);

namespace Crell\PGTools;

interface StoredFunction extends StoredProcedure
{
    public function returns(): string;
}
