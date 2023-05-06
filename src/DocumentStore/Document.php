<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\Attributes\Boolean;
use Crell\PGTools\Attributes\DefaultPartition;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Id;
use Crell\PGTools\Attributes\JsonB;
use Crell\PGTools\Attributes\PartitionByList;
use Crell\PGTools\Attributes\PartitionList;
use Crell\PGTools\Attributes\Table;
use Crell\PGTools\Attributes\TimestampWithTimezone;
use Crell\PGTools\Attributes\Uuid;
use Crell\PGTools\Attributes\Varchar;

#[Table(name: 'document')]
#[PartitionByList('active')]
#[PartitionList('active', [true])]
#[DefaultPartition('revisions')]
class Document
{
    #[Uuid, Id]
    public string $revision;

    #[Uuid]
    public string $uuid;

//    #[Uuid]
//    public ?string $parent = null;

    public bool $latest;

    #[Id]
    public bool $active;

    #[Field(default: false), Boolean]
    public readonly bool $deleted;

    #[Field(default: 'CURRENT_TIMESTAMP'), TimestampWithTimezone]
    public readonly \DateTimeImmutable $created;

    #[Varchar]
    public readonly string $class;

    #[JsonB(classColumn: 'class')]
    public readonly object $document;
}
