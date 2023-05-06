<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\Serde\Serde;
use Crell\Serde\SerdeCommon;

class Connection
{
    public const PgDateTimeString = 'Y-m-d H:i:s.u P';

    public function __construct(
        readonly private \PDO $pdo,
        readonly public Serde $serde = new SerdeCommon(),
        readonly public ClassAnalyzer $analyzer = new MemoryCacheAnalyzer(new Analyzer()),
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function schema(): Schema
    {
        return new Schema($this);
    }

    public function documentStore(string $name): DocumentStore
    {
        return new DocumentStore($this);
    }

    /**
     * @param array<string, string> $args
     */
    public function preparedQuery(string $sql, array $args, ?string $into = null): Statement
    {
        $stmt = $this->prepare($sql, $into);
        $stmt->execute($args);
        return $stmt;
    }

    public function prepare(string $sql, ?string $into = null): Statement
    {
        return Statement::forQuery($this, $this->pdo, $sql, $into);
    }

    public function literalQuery(string $sql, ?string $into = null): Statement
    {
        $result = $this->pdo->query($sql);
        return Statement::forStatement($this, $result, $into);
    }

    /**
     *
     * Call like so:
     * [$placeholders, $values] = $conn->toParameterList($list_of_vals);
     *
     * @param array<string|int|float|bool> $vals
     * @return array{array<string>, array<string, mixed>}
     *   A tuple, consisting of an array of placeholders and an array of
     *   values mapped to placeholders.
     */
    public function toParameterList(array $vals): array
    {
        $counter = 1;

        $placeholders = [];
        $values = [];
        foreach ($vals as $val) {
            $placeholder = ':placeholder_' . $counter++;
            $placeholders[] = $placeholder;
            $values[$placeholder] = $val;
        }

        return [$placeholders, $values];
    }

    public function inTransaction(\Closure $c): bool
    {
        // @todo Some logging would be nice in here.
        try {
            $this->pdo->beginTransaction();
            $ret = $c($this);
            if ($ret === false) {
                $this->pdo->rollBack();
                return false;
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function callFunc(string $function, int|string|float|bool ...$args): Statement
    {
        [$placeholders, $values] = $this->toParameterList($args);

        $query = "SELECT * FROM $function(" . implode(', ', $placeholders) . ')';
        return $this->preparedQuery($query, $values);
    }

    public function callProc(string $procedure, int|string|float|bool ...$args): Statement
    {
        [$placeholders, $values] = $this->toParameterList($args);

        $query = "CALL $procedure(" . implode(', ', $placeholders) . ')';
        return $this->preparedQuery($query, $values);
    }

    public function dtiToSql(\DateTimeInterface $date): string
    {
        return $date->format(self::PgDateTimeString);
    }

    public function toSqlLiteral(mixed $value): string
    {
        return match (get_debug_type($value)) {
            'int', 'float' => $value,
            'bool' => $value ? 'true' : 'false',
            'string' => "'$value'",
            default => $value,
        };
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string
    {
        return $this->pdo->quote($string, $type);
    }
}
