<?php

declare(strict_types=1);

namespace Crell\PGTools\Tables;

use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Id;
use Crell\PGTools\Attributes\Serial;
use Crell\PGTools\Attributes\Table;
use Crell\PGTools\Attributes\TimestampWithTimezone;
use Crell\PGTools\Attributes\Varchar;

#[Table]
class PrimitiveTypes
{
    #[Id, Serial]
    public readonly int $id;

    #[Field]
    public readonly int $int;

    public readonly float $float;

    #[Varchar]
    public readonly string $string;

    #[Field, TimestampWithTimezone]
    public readonly \DateTimeImmutable $datetime;
}
