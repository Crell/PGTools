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
            ':document' => '{}',
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

        self::assertEquals('Larry', json_decode($records[1]['document'], true)['name']);
    }
}

/**
 *

 * create table data
(
id       serial
constraint data_pk
primary key,
created  timestamptz default now(),
modified timestamptz default now(),
document jsonb
);

 */
