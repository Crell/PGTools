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
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        return $stmt;
    }

    public function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        return $stmt;
    }

    public function literalQuery(string $sql): \PDOStatement
    {
        $result = $this->pdo->query($sql);
        $result->setFetchMode(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function installProcedure(StoredProcedure $proc): void
    {
//        $sql = sprintf("CREATE OR REPLACE PROCEDURE %s RETURNS %s "
    }

    public function installRawFunction(RawFunction $func): void
    {
        $sql = $func->completeFunction();

        $this->literalQuery($sql);
    }
}
