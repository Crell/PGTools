<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;
use function Crell\fp\amap;
use function Crell\fp\pipe;

/**
 * @todo How to support multiple named document stores, when tables are 1:1 with classes right now?
 */
class DocumentStore
{
    // @todo Or maybe these should be functions?
    private readonly \PDOStatement $loadStatement;
    private readonly \PDOStatement $loadStatementSingle;
    private readonly \PDOStatement $deleteStatement;
    private readonly \PDOStatement $purgeDeletedStatement;
    private readonly \PDOStatement $purgeOldRevisionsStatement;

    public function __construct(
        private readonly Connection $connection,
        private readonly Serde $serde = new SerdeCommon(),
    ) {
        // @todo This syntax isn't working for some reason. I think
        // PDO is garbling the query due to all the colons.
        // cf: https://stackoverflow.com/questions/10720420/node-postgres-how-to-execute-where-col-in-dynamic-value-list-query
        $this->loadStatement ??= $this->connection->prepare(
            "SELECT document, class 
                    FROM document 
                    WHERE deleted=false 
                      AND active=true
                      AND uuid = ANY(:uuids::uuid[])");
        $this->loadStatementSingle ??= $this->connection->prepare(
            "SELECT document, class
                    FROM document
                    WHERE deleted=false
                      AND active=true
                      AND uuid=:uuid");
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
        $counter = 1;

        $placeholders = [];
        $values = [];
        foreach ($ids as $id) {
            $placeholder = ':placeholder_1' . $counter++;
            $placeholders[] = $placeholder;
            $values[$placeholder] = $id;
        }

        $stmt = $this->connection->prepare('SELECT document, class 
                    FROM document 
                    WHERE deleted=false 
                      AND active=true
                      AND uuid IN (' . implode(',', $placeholders) . ')');

        $stmt->execute($values);

        return pipe($stmt,
            amap(fn(array $record): object => $this->serde->deserialize($record['document'], from: 'json', to: $record['class'])),
        );
    }

    public function load(string $uuid): ?object
    {
        return $this->loadMultiple([$uuid])[0] ?? null;
        /*
        $this->loadStatementSingle->execute([':uuid' => $uuid]);

        $record = $this->loadStatementSingle->fetch();
        if (!$record) {
            return null;
        }

        return $this->serde->deserialize($record['document'], from: 'json', to: $record['class']);
        */
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
