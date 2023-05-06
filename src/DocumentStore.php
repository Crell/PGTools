<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\DocumentStore\Document;
use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;
use function Crell\fp\amap;
use function Crell\fp\indexBy;
use function Crell\fp\pipe;
use function Crell\fp\prop;

/**
 * @todo How to support multiple named document stores, when tables are 1:1 with classes right now?
 */
class DocumentStore
{
    // @todo Or maybe these should be functions?
    private readonly Statement $loadStatement;
    private readonly Statement $loadRevisionsStatement;
    private readonly Statement $deleteStatement;
    private readonly Statement $purgeDeletedStatement;
    private readonly Statement $purgeOldRevisionsStatement;

    public function __construct(
        private readonly Connection $connection,
        private readonly Serde $serde = new SerdeCommon(),
    ) {
        $this->loadStatement ??= $this->connection->prepare(
            "SELECT document, class
                    FROM document
                    WHERE deleted=false
                      AND active=true
                      AND uuid = ANY(:uuids::uuid[])");
        $this->loadRevisionsStatement ??= $this->connection->prepare(
            'SELECT *
                    FROM document
                    WHERE uuid=:uuid
                    ORDER BY created
                    LIMIT :limit OFFSET :offset', into: Document::class);
        $this->deleteStatement ??= $this->connection->prepare(
            "UPDATE document SET deleted=true WHERE uuid=:uuid");
        $this->purgeDeletedStatement ??= $this->connection->prepare(
            "WITH uuids AS (
                    SELECT uuid
                        FROM document
                        WHERE deleted=true
                            AND active=true
                            AND created < :threshold::timestamptz
                        GROUP BY uuid
                    )
                DELETE FROM document USING uuids WHERE document.uuid=uuids.uuid
            ");

        $this->purgeOldRevisionsStatement ??= $this->connection->prepare(
            "DELETE FROM document WHERE active=false AND created < :threshold::timestamptz"
        );
    }

    public function write(object $document): object
    {
        $uuid = $this->connection->callFunc('gen_random_uuid')->fetchColumn();
        $revision = $this->connection->callFunc('gen_random_uuid')->fetchColumn();

        // @todo This is all kinda hacky.  Do better.
        (fn(object $document) => $document->uuid ??= $uuid)
            ->call($document, $document);

        $this->connection->callProc('add_doc_revision',
            $document->uuid,
            $revision,
            true,
            $document::class,
            $this->serde->serialize($document, format: 'json'),
        );

        return $this->load($document->uuid);
    }

    public function loadMultiple(array $ids): array
    {
        $this->loadStatement->execute([':uuids' => $ids]);

        return pipe($this->loadStatement,
            amap(fn(array $record): object => $this->serde->deserialize($record['document'], from: 'json', to: $record['class'])),
        );
    }

    public function load(string $uuid): ?object
    {
        return $this->loadMultiple([$uuid])[0] ?? null;
    }

    /**
     * @param array $ids
     * @return Document[]
     */
    public function loadRecords(array $ids): array
    {
        [$placeholders, $values] = $this->connection->toParameterList($ids);

        $stmt = $this->connection->prepare('SELECT *
                    FROM document
                    WHERE uuid IN (' . implode(',', $placeholders) . ')');

        $stmt->execute($values);

        $docs = [];
        foreach ($stmt as $record) {
            $record['document'] = $this->serde->deserialize($record['document'], from: 'json', to: $record['class']);
            $docs[$record['revision']] = $this->serde->deserialize($record, from: 'array', to: Document::class);
        }

        return $docs;
    }

    public function loadRevisions(string $uuid, int $limit = 10, int $offset = 0): array
    {
        $this->loadRevisionsStatement->execute([
            ':uuid' => $uuid,
            ':limit' => $limit,
            ':offset' => $offset,
        ]);

        $docs = [];
        foreach ($this->loadRevisionsStatement as $record) {
            $docs[$record->revision] = $record;
        }

        return $docs;
    }

    public function delete(string $uuid): void
    {
        $this->deleteStatement->execute([':uuid' => $uuid]);
    }

    public function purgeDeletedOlderThan(\DateTimeImmutable $threshold): void
    {
        $this->purgeDeletedStatement->execute([':threshold' => $this->connection->dtiToSql($threshold)]);
    }

    public function purgeRevisionsOlderThan(\DateTimeImmutable $threshold): void
    {
        $this->purgeOldRevisionsStatement->execute([':threshold' => $this->connection->dtiToSql($threshold)]);
    }
}
