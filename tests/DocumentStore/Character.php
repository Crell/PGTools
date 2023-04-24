<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

class Character
{

    public readonly string $uuid;

    public function __construct(
        public string $name,
        public string $rank,
    ) {}
}
