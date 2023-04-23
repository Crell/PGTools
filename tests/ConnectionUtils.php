<?php

declare(strict_types=1);

namespace Crell\PGTools;

trait ConnectionUtils
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

    protected function initConnection(): void
    {
        $dsn = $this->makeDsn();
        $pdo = new \PDO($dsn);
        $this->connection = new Connection($pdo);
    }
}
