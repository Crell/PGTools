<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\ConnectionUtils;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DocumentStoreTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS document");
        $this->connection->schema()->ensureTable(DocumentStore::class);
    }

    #[Test]
    public function raw_editing_of_document_table(): void
    {
        $stmt = $this->connection->prepare("INSERT INTO document (uuid, document, class) VALUES (:uuid, :document, :class)");
        $stmt->execute([
            ':uuid' => $this->connection->call('gen_random_uuid')->fetchColumn(),
            ':document' => '{"name": "James T Kirk"}',
            ':class' => Character::class,
        ]);

        $records = $this->connection->literalQuery("SELECT * FROM document")->fetchAll();
        self::assertCount(1, $records);
        $firstRecord = $records[0];

        self::assertEquals(Character::class, $firstRecord['class']);

        // Give ourselves just barely enough time for the timestamp to change.
        usleep(10);

        $this->connection->preparedQuery("UPDATE document SET document = :document", [
            ':document' => '{"name": "Jean-Luc Picard"}',
        ]);

        $records = $this->connection->literalQuery("SELECT * FROM document")->fetchAll();
        self::assertCount(1, $records);
        $updatedRecord = $records[0];

        self::assertEquals(Character::class, $updatedRecord['class']);

        // Modified should auto-update.  Created should not.
        self::assertEquals($firstRecord['created'], $updatedRecord['created']);
        self::assertNotEquals($firstRecord['modified'], $updatedRecord['modified']);
    }

    #[Test]
    public function document_store(): void
    {
        $kirk = new Character('James T. Kirk', 'Captain');

        $store = $this->connection->documentStore('main');

        /** @var Character $written */
        $written = $store->write($kirk);

        self::assertNotEmpty($written->uuid);
        self::assertEquals('James T. Kirk', $written->name);
        self::assertEquals('Captain', $written->rank);

        $written->rank = 'Admiral';
        $store->write($written);

        $updated = $store->load($written->uuid);

        self::assertEquals('James T. Kirk', $updated->name);
        self::assertEquals('Admiral', $updated->rank);
    }

    #[Test]
    public function delete_works(): void
    {
        $kirk = new Character('James T. Kirk', 'Captain');

        $store = $this->connection->documentStore('main');

        /** @var Character $written */
        $written = $store->write($kirk);

        self::assertNotEmpty($written->uuid);
        self::assertEquals('James T. Kirk', $written->name);
        self::assertEquals('Captain', $written->rank);

        $store->delete($written->uuid);

        $reload = $store->load($written->uuid);

        self::assertNull($reload);

        $rawRecord = $this->connection->preparedQuery("SELECT * FROM document WHERE uuid=:uuid", [
            ':uuid' => $written->uuid,
        ])
            ->fetch();

        self::assertTrue($rawRecord['deleted']);
        self::assertSame('Captain', json_decode($rawRecord['document'])->rank);
    }
}
