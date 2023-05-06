<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Tables\ArrayExample;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS arrayexample");
        $this->connection->schema()->ensureTable(ArrayExample::class);

//        $this->runFixtures();
    }

    #[Test]
    public function transactions_success(): void
    {
        $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
            ':series' => 'TOS',
            ':characters' => ['Kirk', 'Spock', 'McCoy'],
        ]);

        $this->connection->inTransaction(function (Connection $conn) {
            $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
                ':series' => 'TNG',
                ':characters' => ['Picard', 'Riker', 'Data'],
            ]);
        });

        $numRows = $this->connection->literalQuery('SELECT COUNT(*) FROM arrayexample')->fetchColumn();
        self::assertEquals(2, $numRows);
    }

    #[Test]
    public function transactions_rollback_exception(): void
    {
        $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
            ':series' => 'TOS',
            ':characters' => ['Kirk', 'Spock', 'McCoy'],
        ]);

        $this->connection->inTransaction(function (Connection $conn) {
            $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
                ':series' => 'TNG',
                ':characters' => ['Picard', 'Riker', 'Data'],
            ]);
            throw new \Exception('Oopsies');
        });

        $numRows = $this->connection->literalQuery('SELECT COUNT(*) FROM arrayexample')->fetchColumn();
        self::assertEquals(1, $numRows);
    }

    #[Test]
    public function transactions_rollback_return(): void
    {
        $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
            ':series' => 'TOS',
            ':characters' => '{"Kirk", "Spock", "McCoy"}',
        ]);

        $this->connection->inTransaction(function (Connection $conn) {
            $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
                ':series' => 'TNG',
                ':characters' => '{"Picard", "Riker", "Data"}',
            ]);
            return false;
        });

        $numRows = $this->connection->literalQuery('SELECT COUNT(*) FROM arrayexample')->fetchColumn();
        self::assertEquals(1, $numRows);
    }

}
