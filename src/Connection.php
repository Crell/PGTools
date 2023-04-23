<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\SequenceField;
use Crell\PGTools\Attributes\Table;
use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;

class Connection
{
    public function __construct(
        readonly private \PDO $pdo,
        readonly private Serde $serde = new SerdeCommon(),
        readonly private ClassAnalyzer $analyzer = new MemoryCacheAnalyzer(new Analyzer()),
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function schema(): Schema
    {
        return new Schema($this);
    }

    /**
     *
     * @todo Might this be better as a custom result type? That may say where to put array decoding.
     *
     * @param class-string $class
     */
    public function fetchInto(\PDOStatement $result, string $class): \Generator
    {
        $fields = $this->analyzer->analyze($class, Table::class)->fields;
        $sequenceColumns = array_filter($fields, static fn(Field $f): bool => $f->columnType instanceof SequenceField);
        foreach ($result as $record) {
            foreach ($sequenceColumns as $name => $field) {
                $record[$name] = $this->decodePgArray($record[$name]);
            }
            yield $this->serde->deserialize($record, from: 'array', to: $class);
        }
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


    /**
     * Shamelessly borrowed from https://stackoverflow.com/questions/3068683/convert-postgresql-array-to-php-array
     *
     * @todo This clearly belongs in a real utility, not a test, but not sure where.
     *
     * @todo It may also be better to use the JSON track suggested in the above thread.
     *
     * @param string $s
     * @param int $start
     * @param $end
     * @return ?array
     */
    public function decodePgArray(string $s, int $start = 0, &$end = null): ?array
    {
        if (empty($s) || $s[0] !== '{') return null;
        $return = array();
        $string = false;
        $quote='';
        $len = strlen($s);
        $v = '';
        for ($i = $start + 1; $i < $len; $i++) {
            $ch = $s[$i];

            if (!$string && $ch === '}') {
                if ($v !== '' || !empty($return)) {
                    $return[] = $v;
                }
                $end = $i;
                break;
            } elseif (!$string && $ch === '{') {
                $v = $this->decodePgArray($s, $i, $i);
            } elseif (!$string && $ch === ','){
                $return[] = $v;
                $v = '';
            } elseif (!$string && ($ch === '"' || $ch === "'")) {
                $string = true;
                $quote = $ch;
            } elseif ($string && $ch === $quote && $s[$i - 1] === "\\") {
                $v = substr($v, 0, -1) . $ch;
            } elseif ($string && $ch === $quote && $s[$i - 1] !== "\\") {
                $string = false;
            } else {
                $v .= $ch;
            }
        }

        return $return;
    }
}
