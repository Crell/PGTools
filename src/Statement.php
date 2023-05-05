<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Exception;
use Traversable;

class Statement implements \IteratorAggregate
{
    private \PDOStatement $pdoStatement;

    public function __construct(
        private readonly Connection $connection,
        \PDO $pdo,
        string $query,
        private readonly ?string $into = null,
    ) {
        $this->pdoStatement = $pdo->prepare($query);
        if ($this->into) {
            $this->pdoStatement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $this->into);
        } else {
            $this->pdoStatement->setFetchMode(\PDO::FETCH_ASSOC);
        }
    }

    public function execute(array $args): static
    {
        $this->pdoStatement->execute($args);
        return $this;
    }

    public function fetch(): array|object|false
    {
        // @todo this won't work if we want to handle array fields.
        return $this->pdoStatement->fetch();
    }

    public function fetchColumn(int $column = 0): int|string|float|array|false
    {
        return $this->pdoStatement->fetchColumn($column);
    }

    public function fetchAll(): array
    {
        return iterator_to_array($this);
    }

    public function getIterator(): Traversable
    {
        yield from $this->pdoStatement;
    }

}
