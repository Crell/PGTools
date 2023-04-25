<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Procedures\FindPeople;
use Crell\PGTools\Procedures\HasPerson;
use Crell\PGTools\Procedures\HasPersonRaw;
use Crell\PGTools\Tables\Data;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StoredProcedureTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS data");
        $this->connection->schema()->ensureTable(Data::class);

        $this->runFixtures();
    }

    public function tearDown(): void
    {
        $this->connection->literalQuery("DELETE FROM data");
    }

    private function runFixtures(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO data (document) VALUES (:document)');

        $stmt->execute([
            ':document' => '{"name": "James"}',
        ]);
        $stmt->execute([
            ':document' => '{"name": "Larry"}',
        ]);
    }

    #[Test]
    public function basic_fetch_with_json(): void
    {
        $result = $this->connection->literalQuery("SELECT * FROM data");

        $records = $result->fetchAll();

        self::assertCount(2, $records);
        self::assertEquals('{"name": "Larry"}', $records[1]['document']);
        self::assertEquals('Larry', json_decode($records[1]['document'], true, 512, JSON_THROW_ON_ERROR)['name']);
    }

    #[Test]
    public function literalproc(): void
    {
        $this->connection->schema()->installRawFunction(new HasPersonRaw());

        $stmt = $this->connection->literalQuery("SELECT has_person_raw('Larry')");

        self::assertTrue($stmt->fetchColumn());
    }

    #[Test]
    public function structured_proc(): void
    {
        $this->connection->schema()->installProcedure(new HasPerson());

        $stmt = $this->connection->literalQuery("SELECT * FROM has_person('Larry')");

        self::assertTrue($stmt->fetchColumn());
    }

    #[Test]
    public function table_return(): void
    {
        $this->connection->schema()->installProcedure(new FindPeople());

        $stmt = $this->connection->literalQuery("SELECT * FROM find_people('Larry')");

        $records = $stmt->fetchAll();

        self::assertCount(1, $records);
        self::assertEquals('{"name": "Larry"}', $records[0]['doc']);
        self::assertEquals('Larry', json_decode($records[0]['doc'], true, 512, JSON_THROW_ON_ERROR)['name']);
    }

    #[Test]
    public function call_table_return(): void
    {
        $this->connection->schema()->installProcedure(new FindPeople());

        $result = $this->connection->call('find_people', 'Larry');

        $records = $result->fetchAll();

        self::assertCount(1, $records);
        self::assertEquals('{"name": "Larry"}', $records[0]['doc']);
        self::assertEquals('Larry', json_decode($records[0]['doc'], true, 512, JSON_THROW_ON_ERROR)['name']);
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
