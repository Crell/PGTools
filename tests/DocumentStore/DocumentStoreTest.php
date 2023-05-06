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
            (uuid, revision, latest, active, document, class)
            VALUES (:uuid, :revision, :latest, :active, :document, :class)
        ");
        $stmt->execute([
            ':uuid' => $this->connection->callFunc('gen_random_uuid')->fetchColumn(),
            ':revision' => $this->connection->callFunc('gen_random_uuid')->fetchColumn(),
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
    public function load_multple(): void
    {
        $picard = new Character('Jean-Luc Picard', 'Captain');
        $riker = new Character('William Riker', 'Commander');

        $store = $this->connection->documentStore('main');

        /** @var Character $written */
        $picard = $store->write($picard);
        $riker = $store->write($riker);

        $chars = $store->loadMultiple([$picard->uuid, $riker->uuid]);

        self::assertCount(2, $chars);
        self::assertEquals('Captain', $picard->rank);
        self::assertEquals('Commander', $riker->rank);
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

    #[Test]
    public function partitioning_puts_only_active_records_in_active_table(): void
    {
        $orig = new Character('Spock', 'Lt. Cmdr.');

        $store = $this->connection->documentStore('main');

        /** @var Character $spock */
        $spock = $store->write($orig);

        $spock->rank = 'Commander';
        $store->write($spock);
        $spock->rank = 'Captain';
        $store->write($spock);
        $spock->rank = 'Ambassador';
        $store->write($spock);

        $activeCount = $this->connection->preparedQuery("SElECT COUNT(*) FROM document_active WHERE uuid=:uuid", [
            ':uuid' => $spock->uuid,
        ])->fetchColumn();

        self::assertEquals(1, $activeCount);

        $revisionCount = $this->connection->preparedQuery("SElECT COUNT(*) FROM document_revisions WHERE uuid=:uuid", [
            ':uuid' => $spock->uuid,
        ])->fetchColumn();

        self::assertEquals(3, $revisionCount);
    }

    #[Test]
    public function load_old_revisions(): void
    {
        $orig = new Character('Spock', 'Lt. Cmdr.');

        $store = $this->connection->documentStore('main');

        /** @var Character $spock */
        $spock = $store->write($orig);

        $uuid = $spock->uuid;

        $spock->rank = 'Commander';
        $store->write($spock);
        $spock->rank = 'Captain';
        $store->write($spock);
        $spock->rank = 'Ambassador';
        $store->write($spock);

        $records = $store->loadRevisions($uuid, 10, 0);

        self::assertCount(4, $records);
        foreach ($records as $rec) {
            self::assertInstanceOf(Document::class, $rec);
            self::assertInstanceOf(Character::class, $rec->document);
        }
    }
}
