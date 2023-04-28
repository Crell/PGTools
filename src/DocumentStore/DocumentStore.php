<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\Attributes\Boolean;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Id;
use Crell\PGTools\Attributes\JsonB;
use Crell\PGTools\Attributes\Table;
use Crell\PGTools\Attributes\TimestampWithTimezone;
use Crell\PGTools\Attributes\Trigger;
use Crell\PGTools\Attributes\Uuid;
use Crell\PGTools\Attributes\Varchar;

#[Table(name: 'document')]
#[Trigger(UpdateModifiedDate::class)]
class DocumentStore
{
    #[Uuid, Id]
    public string $uuid;

    #[Field(default: 'CURRENT_TIMESTAMP'), TimestampWithTimezone]
    public readonly \DateTimeImmutable $created;

    #[Field(default: 'CURRENT_TIMESTAMP'), TimestampWithTimezone]
    public readonly \DateTimeImmutable $modified;

    #[Varchar]
    public readonly string $class;

    #[Field(default: false), Boolean]
    public readonly bool $deleted;

    #[JsonB]
    public readonly string $document;
}
