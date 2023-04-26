<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;

/**
 * @todo How to support multiple named document stores, when tables are 1:1 with classes right now?
 */
class DocumentStore
{
    // @todo Or maybe these should be functions?
    private readonly \PDOStatement $insertStatement;
    private readonly \PDOStatement $updateStatement;
    private readonly \PDOStatement $loadStatement;
    private readonly \PDOStatement $deleteStatement;

    public function __construct(
        private readonly Connection $connection,
        private readonly Serde $serde = new SerdeCommon(),
    ) {
        $this->insertStatement
            ??= $this->connection->prepare("INSERT INTO document (uuid, class, document) VALUES (:uuid, :class, :document)");
        $this->updateStatement
            ??= $this->connection->prepare("UPDATE document SET document = :document WHERE uuid=:uuid");
        $this->loadStatement
            ??= $this->connection->prepare("SELECT * FROM document WHERE deleted=false AND uuid=:uuid");
        $this->deleteStatement
            ??= $this->connection->prepare("UPDATE document SET deleted=true WHERE uuid=:uuid");
    }

    public function write(object $document): object
    {
        if (!isset($document->uuid)) {
            // Assume it's a new object.

            $uuid = $this->connection->call('gen_random_uuid')->fetchColumn();

            // @todo This is all kinda hacky.  Do better.
            (fn(object $document) => $document->uuid = $uuid)
                ->call($document, $document);

            $this->insertStatement->execute([
                ':uuid' => $document->uuid,
                ':class' => $document::class,
                ':document' => $this->serde->serialize($document, format: 'json'),
            ]);
            return $this->load($uuid);
        } else {
            $this->updateStatement->execute([
                ':uuid' => $document->uuid,
                ':document' => $this->serde->serialize($document, format: 'json'),
            ]);
            return $document;
        }
    }

    public function load(string $uuid): ?object
    {
        $this->loadStatement->execute([':uuid' => $uuid]);

        $record = $this->loadStatement->fetch();
        if (!$record) {
            return null;
        }

        return $this->serde->deserialize($record['document'], from: 'json', to: $record['class']);
    }

    public function delete(string $uuid): void
    {
        $this->deleteStatement->execute([':uuid' => $uuid]);
    }
}
