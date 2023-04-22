<?php

declare(strict_types=1);

namespace Crell\PGTools;

interface StoredProcedure
{
    public function name(): string;

    public function language(): StoredLanguage;

    public function body(): string;

    public function parameters(): array;
}
