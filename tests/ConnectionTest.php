<?php

declare(strict_types=1);

namespace Crell\PGTools;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private function makeDsn(): string
    {
        return 'pgsql:host=' . getenv('DB_HOST')
            . ';port=' . getenv('DB_PORT')
            . ';user=' . getenv('DB_USER')
            . ';password=' . getenv('DB_PASS')
            . ';dbname=' . getenv('DB_NAME');
    }

    #[Test]
    public function stuff(): void
    {
        $dsn = $this->makeDsn();
        $pdo = new \PDO($dsn);
//        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo);

        $conn->preparedQuery("INSERT INTO data (document) VALUES (:document)", [
            ':document' => '{}',
        ]);

        $result = $conn->literalQuery("SELECT * FROM data");

        foreach ($result as $record) {
            print_r($record);
        }
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
