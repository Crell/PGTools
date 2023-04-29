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
        $this->connection->schema()->ensureTable(Document::class);
        $this->connection->schema()->installProcedure(new AddDocRevision());
    }

    #[Test]
    public function raw_editing_of_document_table(): void
    {
        $stmt = $this->connection->prepare("INSERT INTO document
            (uuid, revision, parent, latest, active, document, class)
            VALUES (:uuid, :revision, :parent, :latest, :active, :document, :class)
        ");
        $stmt->execute([
            ':uuid' => $this->connection->callFunc('gen_random_uuid')->fetchColumn(),
            ':revision' => $this->connection->callFunc('gen_random_uuid')->fetchColumn(),
            ':parent' => null,
            ':latest' => true,
            ':active' => true,
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

        self::assertEquals($firstRecord['created'], $updatedRecord['created']);
    }

    #[Test]
    public function save_and_load(): void
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

    #[Test]
    public function purge_deleted_works(): void
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

        // Timestamps are down to zillionths of a second, so this should
        // clear out the one we just deleted.
        $store->purgeDeletedOlderThan(new \DateTimeImmutable());

        $rawRecord = $this->connection->preparedQuery("SELECT uuid FROM document WHERE uuid=:uuid", [
            ':uuid' => $written->uuid,
        ])
            ->fetch();

        self::assertFalse($rawRecord);
    }

    #[Test]
    public function purge_old_revisions_works(): void
    {
        $kirk = new Character('James T. Kirk', 'Captain');

        $store = $this->connection->documentStore('main');

        /** @var Character $written */
        $written = $store->write($kirk);

        $uuid = $written->uuid;

        self::assertNotEmpty($written->uuid);
        self::assertEquals('James T. Kirk', $written->name);
        self::assertEquals('Captain', $written->rank);

        $threshold = new \DateTimeImmutable();

        $written->rank = 'Admiral';
        $store->write($written);

        $store->purgeRevisionsOlderThan($threshold);

        $records = $this->connection->preparedQuery("SELECT uuid FROM document WHERE uuid=:uuid", [
            ':uuid' => $uuid,
        ])
            ->fetchAll();

        self::assertCount(1, $records);
        self::assertSame($uuid, $records[0]['uuid']);
    }
}
