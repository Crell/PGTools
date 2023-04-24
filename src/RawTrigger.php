<?php

declare(strict_types=1);

namespace Crell\PGTools;

interface RawTrigger
{
    public function completeTrigger(): string;

    public function triggerFunction(): StoredFunction|RawFunction;
}
