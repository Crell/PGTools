<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Tables\ArrayExample;
use Crell\PGTools\Tables\PrimitiveTypes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS PrimitiveTypes");
        //$this->connection->schema()->ensureTable(PrimitiveTypes::class);

//        $this->runFixtures();
    }

    #[Test]
    public function literal_query(): void
    {
        $this->connection->schema()->ensureTable(PrimitiveTypes::class);

        $dti = $this->connection->dtiToSql(new \DateTimeImmutable('2021-07-04 14:00', new \DateTimeZone('America/Chicago')));

        $this->connection->literalQuery(sprintf("INSERT INTO PrimitiveTypes 
            (int, float, string, datetime) 
            VALUES (%d, %f, '%s', '%s')", 5, 3.14, 'hello', $dti));

        $result = $this->connection->literalQuery("SELECT * FROM PrimitiveTypes WHERE int=5");

        $record = $result->fetch();

        $expected = [
            'id' => 1,
            'int' => 5,
            'float' => 3.14,
            'string' => 'hello',
            // Postgres returns dates in UTC normalized format, I guess?
            'datetime' => '2021-07-04 19:00:00+00',
        ];

        self::assertEquals($expected, $record);
    }

    #[Test]
    public function prepared_statements(): void
    {
        $this->connection->schema()->ensureTable(PrimitiveTypes::class);

        $stmt = $this->connection->prepare('INSERT INTO PrimitiveTypes 
            (int, float, string, datetime) 
            VALUES (:int, :float, :string, :datetime)');

        $stmt->execute([1, 3.14, 'hello', new \DateTimeImmutable('2021-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([10, 3.14, 'goodbye', new \DateTimeImmutable('2022-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([20, 3.14, 'hi again', new \DateTimeImmutable('2023-07-04 14:00', new \DateTimeZone('America/Chicago'))]);

        $stmt = $this->connection->literalQuery('SELECT * FROM PrimitiveTypes');

        $records = $stmt->fetchAll();

        self::assertCount(3, $records);
        self::assertEquals(1, $records[0]['int']);
        self::assertEquals(3.14, $records[1]['float']);
        self::assertEquals('hi again', $records[2]['string']);
    }

    #[Test]
    public function fetch(): void
    {
        $this->connection->schema()->ensureTable(PrimitiveTypes::class);

        $stmt = $this->connection->prepare('INSERT INTO PrimitiveTypes 
            (int, float, string, datetime) 
            VALUES (:int, :float, :string, :datetime)');

        $stmt->execute([1, 3.14, 'hello', new \DateTimeImmutable('2021-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([10, 3.14, 'goodbye', new \DateTimeImmutable('2022-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([20, 3.14, 'hi again', new \DateTimeImmutable('2023-07-04 14:00', new \DateTimeZone('America/Chicago'))]);

        $stmt = $this->connection->literalQuery('SELECT * FROM PrimitiveTypes');

        $record = $stmt->fetch();

        self::assertEquals(1, $record['int']);
        self::assertEquals(3.14, $record['float']);
        self::assertEquals('hello', $record['string']);
    }

    #[Test]
    public function fetchColumn(): void
    {
        $this->connection->schema()->ensureTable(PrimitiveTypes::class);

        $stmt = $this->connection->prepare('INSERT INTO PrimitiveTypes 
            (int, float, string, datetime) 
            VALUES (:int, :float, :string, :datetime)');

        $stmt->execute([1, 3.14, 'hello', new \DateTimeImmutable('2021-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([10, 3.14, 'goodbye', new \DateTimeImmutable('2022-07-04 14:00', new \DateTimeZone('America/Chicago'))]);
        $stmt->execute([20, 3.14, 'hi again', new \DateTimeImmutable('2023-07-04 14:00', new \DateTimeZone('America/Chicago'))]);

        $stmt = $this->connection->literalQuery('SELECT int, float, string FROM PrimitiveTypes');

        self::assertEquals(1, $stmt->fetchColumn(0));
        self::assertEquals(3.14, $stmt->fetchColumn(1));
        self::assertEquals('hi again', $stmt->fetchColumn(2));
    }

    /**
     * @todo Arrays coming back as strings with oddball quoting is not ideal.
     *   Fixing that, however, requires knowledge of the field type, which
     *   PDO doesn't really give us.  That may only work when fetching into
     *   a class so that we have the type data there to guide us.
     */
    #[Test]
    public function array_quoting(): void
    {
        $this->connection->literalQuery("DROP TABLE IF EXISTS arrayexample");
        $this->connection->schema()->ensureTable(ArrayExample::class);

        $this->connection->preparedQuery('insert into arrayexample (series, characters) VALUES (:series, :characters)', [
            ':series' => 'VOY',
            ':characters' => ['Janeway', 'Tuvok', "B'Elana"],
        ]);

        $record = $this->connection->literalQuery('SELECT * FROM arrayexample')->fetch();

        self::assertEquals('VOY', $record['series']);
        self::assertEquals("{'Janeway','Tuvok','B''Elana'}", $record['characters']);
    }
}
