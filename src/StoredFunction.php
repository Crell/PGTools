<?php

declare(strict_types=1);

namespace Crell\PGTools;

interface StoredFunction extends StoredProcedure
{
    public function returns(): string;

    public function name(): string;

    public function language(): StoredLanguage;

    public function body(): string;

    public function parameters(): array;
}
