<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Tables\ArrayExample;
use Crell\PGTools\Tables\Data;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS data");
        $this->connection->literalQuery("DROP TABLE IF EXISTS ArrayExample");
    }

    #[Test]
    public function table_creation_works(): void
    {
        $schema = $this->connection->schema();
        $schema->ensureTable(Data::class);

        $stmt = $this->connection->prepare('INSERT INTO data (document) VALUES (:document)');

        $stmt->execute([
            ':document' => '{"name": "Larry"}',
        ]);

        $found = $this->connection->literalQuery("SELECT count(*) 
          FROM data
          WHERE cast(document->>'name' AS text) = 'Larry'")->fetchColumn();

        self::assertSame(1, $found);
    }

    #[Test]
    public function array_columns_work(): void
    {
        $schema = $this->connection->schema();

        $schema->ensureTable(ArrayExample::class);

        $stmt = $this->connection->prepare('INSERT INTO ArrayExample (series, characters) VALUES (:series, :characters)');

        $stmt->execute([
            'series' => 'Star Wars',
            'characters' => '{"Luke", "Leia", "Han"}',
        ]);
        $stmt->execute([
            'series' => 'Voyager',
            'characters' => '{"Janeway", "Chakotay", "B\'Elana"}',
        ]);

        $records = $this->connection->literalQuery("SELECT * FROM ArrayExample WHERE series='Star Wars'")->fetchAll();

        self::assertCount(1, $records);

        self::assertSame('{Luke,Leia,Han}', $records[0]['characters']);
    }
}
