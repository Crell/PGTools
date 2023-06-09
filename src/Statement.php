<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Attributes\DeserializesToObject;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Result;
use Crell\PGTools\Attributes\SelfDecodingColumn;
use Traversable;
use function Crell\fp\amap;
use function Crell\fp\implode;
use function Crell\fp\pipe;

class Statement implements \IteratorAggregate
{
    private \PDOStatement $pdoStatement;

    private readonly Connection $connection;

    private readonly ?string $into;

    /**
     * For caching.
     */
    private readonly \Closure $populator;

    private function __construct() {}

    public static function forQuery(
        Connection $connection,
        \PDO $pdo,
        string $query,
        ?string $into = null
    ): self {
        return self::forStatement($connection, $pdo->prepare($query), $into);
    }

    public static function forStatement(
        Connection $connection,
        \PDOStatement $statement,
        ?string $into = null
    ): self {
        $new = new self();
        $new->connection = $connection;
        $new->into = $into;
        $new->pdoStatement = $statement;
        $new->pdoStatement->setFetchMode(\PDO::FETCH_ASSOC);
        return $new;
    }

    /**
     * @param array<string|int, mixed> $args
     *   A PDO-style arguments array, indexed by placeholder name. Placeholders have a ':' prefix.
     * @return $this
     */
    public function execute(array $args): static
    {
        $args = array_map($this->preprocessQueryArg(...), $args);

        $this->pdoStatement->execute($args);
        return $this;
    }

    /**
     * @return array<int, mixed>|object|false
     */
    public function fetch(): array|object|false
    {
        $record = $this->pdoStatement->fetch();
        if ($record === false) {
            return false;
        }
        if (is_null($this->into)) {
            return $record;
        }

        $resultDef = $this->connection->analyzer->analyze($this->into, Result::class);
        return $this->createObject($this->into, $record, $resultDef);
    }

    /**
     *
     *
     * @param int $column
     * @return int|string|float|array<int|string, mixed>|bool
     */
    public function fetchColumn(int $column = 0): int|string|float|array|bool
    {
        return $this->pdoStatement->fetchColumn($column);
    }

    /**
     *
     *
     * @return array<array<int|string, mixed>>
     */
    public function fetchAll(): array
    {
        return iterator_to_array($this);
    }

    public function getIterator(): Traversable
    {
        return (function () {
            while($record = $this->fetch()) {
                yield $record;
            }
        })();
    }

    private function preprocessQueryArg(mixed $arg): mixed
    {
        return match (true) {
            $arg instanceof \DateTimeInterface => $this->connection->dtiToSql($arg),
            is_array($arg) => $this->toPgArray($arg),
            default => $arg,
        };
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function toPgArray(array $array): string
    {
        return '{' . pipe($array,
            amap(fn($arg) => is_string($arg) ? "\"$arg\"" : $arg),
            implode(','),
        ) . '}';
    }

    /**
     * @param string $class
     * @param array<string, string|array<string>|true> $record
     * @param Result $def
     * @return object
     */
    private function createObject(string $class, array $record, Result $def): object
    {
        // Make an empty instance of the target class.
        $rClass = new \ReflectionClass($class);
        $new = $rClass->newInstanceWithoutConstructor();

        $this->populator ??= function (array $values) {
            foreach ($values as $k => $v) {
                $this->$k = $v;
            }
        };

        $values = [];
        /** @var Field $field */
        foreach ($def->fields as $field) {
            $val = $record[$field->column] ?? throw ResultTypeColumnMissing::create($field, $class);
            $values[$field->name] = match (true) {
                $field->columnType instanceof SelfDecodingColumn
                    => $field->columnType->decode($val),
                $field->columnType instanceof DeserializesToObject
                    => $this->connection->serde->deserialize($val, from: 'json', to: $field->columnType->className($values)),
                default => $val,
            };
        }

        $this->populator->call($new, $values);

        // @todo This could be a useful feature, maybe.
        // Invoke any post-load callbacks, even if they're private.
//        $methodCaller = fn(string $fn) => $this->$fn();
//        $invoker = $methodCaller->bindTo($new, $new);
//        foreach ($def->postLoad as $fn) {
//            $invoker($fn);
//        }

        return $new;
    }
}
