<?php

declare(strict_types=1);

namespace Crell\PGTools\Tables;

use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\SequenceField;
use Crell\PGTools\Attributes\Table;

#[Table]
class ArrayExample
{
    #[Field]
    public string $series;

    #[SequenceField('varchar')]
    public array $characters;
}
