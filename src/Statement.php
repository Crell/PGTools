<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Traversable;

class Statement implements \IteratorAggregate
{
    private \PDOStatement $pdoStatement;

    private readonly Connection $connection;

    private readonly ?string $into;

    private function __construct() {}

    public static function forQuery(
        Connection $connection,
        \PDO $pdo,
        string $query,
        ?string $into = null
    ): static {
        return self::forStatement($connection, $pdo->prepare($query), $into);
    }

    public static function forStatement(
        Connection $connection,
        \PDOStatement $statement,
        ?string $into = null
    ): static {
        $new = new self();
        $new->connection = $connection;
        $new->into = $into;
        $new->pdoStatement = $statement;
        if ($new->into) {
            $new->pdoStatement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $into);
        } else {
            $new->pdoStatement->setFetchMode(\PDO::FETCH_ASSOC);
        }
        return $new;
    }

    public function execute(array $args): static
    {
        foreach ($args as $key => $arg) {
            if ($arg instanceof \DateTimeInterface) {
                $args[$key] = $this->connection->dtiToSql($arg);
            }
        }

        $this->pdoStatement->execute($args);
        return $this;
    }

    public function fetch(): array|object|false
    {
        // @todo this won't work if we want to handle array fields.
        return $this->pdoStatement->fetch();
    }

    public function fetchColumn(int $column = 0): int|string|float|array|bool
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
