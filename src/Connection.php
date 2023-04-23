<?php

declare(strict_types=1);

namespace Crell\PGTools;

class Connection
{
    public function __construct(readonly private \PDO $pdo) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function schema(): Schema
    {
        return new Schema($this);
    }

    /**
     * @param array<string, string> $args
     */
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

    public function call(string $function, string ...$args): \PDOStatement
    {
        $placeholders = [];
        $values = [];
        foreach (array_values($args) as $idx => $value) {
            $key = ':placeholder_' . $idx;
            $placeholders[] = $key;
            $values[$key] = $value;
        }

        $query = "SELECT * FROM $function(" . implode(', ', $placeholders) . ')';
        return $this->preparedQuery($query, $values);
    }

    public function installProcedure(StoredProcedure $proc): void
    {
        $isFunc = $proc instanceof StoredFunction;
        $sql = "CREATE OR REPLACE "
            . ($isFunc ? 'FUNCTION ' : 'PROCEDURE ')
            . $proc->name();

        $sql .= "(" . $this->paramsToSql($proc->parameters()) . ")";

        if ($isFunc) {
            $sql .= " RETURNS " . $proc->returns();
        }

        $sql .= ' LANGUAGE ' . $proc->language()->value;

        $sql .= ' AS $$ ' . $proc->body() . ' $$ ';

        $this->literalQuery($sql);
    }

    /**
     * @param array<string, string> $params
     */
    private function paramsToSql(array $params): string
    {
        $ret = [];
        foreach ($params as $name => $type) {
            $ret[] = "$name $type";
        }
        return implode(', ', $ret);
    }

    public function installRawFunction(RawFunction $func): void
    {
        $this->literalQuery($func->completeFunction());
    }
}
