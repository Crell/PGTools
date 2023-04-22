<?php

declare(strict_types=1);

namespace Crell\PGTools;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private Connection $connection;

    private function makeDsn(): string
    {
        return 'pgsql:host=' . getenv('DB_HOST')
            . ';port=' . getenv('DB_PORT')
            . ';user=' . getenv('DB_USER')
            . ';password=' . getenv('DB_PASS')
            . ';dbname=' . getenv('DB_NAME');
    }

    public function setUp(): void
    {
        $dsn = $this->makeDsn();
        $pdo = new \PDO($dsn);
        $this->connection = new Connection($pdo);

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
    public function stuff(): void
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
        $this->connection->installRawFunction(new HasPersonRaw());

        $stmt = $this->connection->literalQuery("SELECT has_person_raw('Larry')");

        self::assertTrue($stmt->fetchColumn());
    }

    #[Test]
    public function structured_proc(): void
    {
        $this->connection->installProcedure(new HasPerson());

        $stmt = $this->connection->literalQuery("SELECT has_person('Larry')");

        self::assertTrue($stmt->fetchColumn());
    }

    #[Test]
    public function table_return(): void
    {
        $this->connection->installProcedure(new FindPeople());

        $stmt = $this->connection->literalQuery("SELECT * FROM find_people('Larry')");

        $records = $stmt->fetchAll();

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
