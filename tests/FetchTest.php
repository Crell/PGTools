<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Procedures\FindPeople;
use Crell\PGTools\Procedures\HasPerson;
use Crell\PGTools\Procedures\HasPersonRaw;
use Crell\PGTools\Tables\ArrayExample;
use Crell\PGTools\Tables\Data;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FetchTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS arrayexample");
        $this->connection->schema()->ensureTable(ArrayExample::class);

        $this->runFixtures();
    }

    private function runFixtures(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO ArrayExample (series, characters) VALUES (:series, :characters)');

        $stmt->execute([
            'series' => 'Star Wars',
            'characters' => '{"Luke", "Leia", "Han"}',
        ]);
        $stmt->execute([
            'series' => 'Voyager',
            'characters' => '{"Janeway", "Chakotay", "Tuvok"}',
        ]);
    }

    #[Test]
    public function fetch_into_object(): void
    {
        $result = $this->connection->literalQuery("SELECT * FROM ArrayExample ORDER BY series");

        $it = $this->connection->fetchInto($result, ArrayExample::class);

        $records = iterator_to_array($it);

        self::assertCount(2, $records);
        self::assertInstanceOf(ArrayExample::class, $records[0]);
        self::assertInstanceOf(ArrayExample::class, $records[1]);
        self::assertSame("Tuvok", $records[1]->characters[2]);
    }
}

/**
 *

 * create table data
(
id       serial
constraint data_pk
primary key,
created_stamp  timestamptz default now(),
modified_stamp timestamptz default now(),
document jsonb
);

 */
