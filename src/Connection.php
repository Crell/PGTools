<?php

declare(strict_types=1);

namespace Crell\PGTools;

class Connection
{
    public function __construct(readonly private \PDO $pdo) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function preparedQuery(string $sql, array $args): \PDOStatement
    {
        var_dump(func_get_args());
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        return $stmt;
    }

    public function literalQuery(string $sql): \PDOStatement
    {
        $result = $this->pdo->query($sql);
        $result->setFetchMode(\PDO::FETCH_ASSOC);
        return $result;
    }
}
