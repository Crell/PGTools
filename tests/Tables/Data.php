<?php

declare(strict_types=1);

namespace Crell\PGTools\Tables;

use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Id;
use Crell\PGTools\Attributes\JsonB;
use Crell\PGTools\Attributes\Table;
use Crell\PGTools\Attributes\TimestampWithTimezone;

#[Table(name: 'data')]
class Data
{
    #[Id(generate: true)]
    public readonly int $id;

    #[Field(column: 'created_stamp', default: 'now()'), TimestampWithTimezone]
    public readonly \DateTimeImmutable $created;

    #[Field(column: 'modified_stamp', default: 'now()'), TimestampWithTimezone]
    public readonly \DateTimeImmutable $modified;

    #[Field, JsonB]
    public readonly string $document;
}
