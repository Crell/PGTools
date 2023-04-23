<?php

declare(strict_types=1);

namespace Crell\PGTools;

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
    }

    #[Test]
    public function stuff(): void
    {
        $schema = new Schema($this->connection);
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
}
