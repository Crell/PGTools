<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\PartitionByList;
use Crell\PGTools\Attributes\PartitionByRange;
use Crell\PGTools\Attributes\Table;
use function Crell\fp\amap;
use function Crell\fp\implode;
use function Crell\fp\pipe;
use function Crell\fp\prop;

class Schema
{
    public function __construct(
        readonly private Connection $connection,
        readonly private ClassAnalyzer $analyzer = new MemoryCacheAnalyzer(new Analyzer()),
    ) {}

    /**
     * @param class-string $class
     */
    public function ensureTable(string $class): void
    {
        /** @var Table $tableDef */
        $tableDef = $this->analyzer->analyze($class, Table::class);

        $columnSql = \implode(",\n", array_map($this->fieldToColumnDef(...), $tableDef->fields));

        // @todo We should probably change ID to PKey or something?
        $idFields = array_filter($tableDef->fields, static fn(Field $f): bool => $f->isId);

        if ($idFields) {
            $columns = \implode(', ', array_map(prop('column'), $idFields));
            $columnSql .= sprintf(",\nPRIMARY KEY(%s)", $columns);
        }

        $sql = "CREATE TABLE $tableDef->name ($columnSql)";

        $partitionSql = [];

        if ($tableDef->partitionDef instanceof PartitionByRange) {
            $sql .= ' PARTITION BY RANGE (' . \implode(', ', $tableDef->partitionDef->columns) . ')';
            foreach ($tableDef->partitionDef->partitions as $partition) {
                $partSql = "CREATE TABLE {$tableDef->name}_{$partition->name} PARTITION OF {$tableDef->name} FOR VALUES FROM ";
                $partSql .= '('
                    . pipe($partition->from, amap($this->connection->toSqlLiteral(...)), implode(', '))
                    . ') TO ('
                    . pipe($partition->to, amap($this->connection->toSqlLiteral(...)), implode(', '))
                    . ') ';

                $partitionSql[] = $partSql;
            }

            if ($tableDef->partitionDef->defaultPartition) {
                $partitionSql[] = "CREATE TABLE {$tableDef->name}_{$tableDef->partitionDef->defaultPartition->name} PARTITION OF {$tableDef->name} DEFAULT ";
            }
        } else if ($tableDef->partitionDef instanceof PartitionByList) {
            $sql .= " PARTITION BY List({$tableDef->partitionDef->column}) ";
            foreach ($tableDef->partitionDef->partitions as $partition) {
                $values = pipe($partition->values,
                    amap($this->connection->toSqlLiteral(...)),
                    implode(', '),
                );
                $partitionSql[] = "CREATE TABLE {$tableDef->name}_{$partition->name} PARTITION OF {$tableDef->name} FOR VALUES IN ({$values})";
            }

            if ($tableDef->partitionDef->defaultPartition) {
                $partitionSql[] = "CREATE TABLE {$tableDef->name}_{$tableDef->partitionDef->defaultPartition->name} PARTITION OF {$tableDef->name} DEFAULT";
            }
        }

        $this->connection->literalQuery($sql);

        // If there were any partitions to create, create them.
        foreach ($partitionSql as $sql) {
            $this->connection->literalQuery($sql);
        }

        // Install any related triggers.
        foreach ($tableDef->triggers as $t) {
            $this->installTrigger(new $t());
        }
    }

    public function installTrigger(RawTrigger $trigger): void
    {
        $function = $trigger->triggerFunction();
        if ($function instanceof RawFunction) {
            $this->installRawFunction($function);
        } else {
            $this->installProcedure($function);
        }
        $this->connection->literalQuery($trigger->completeTrigger());
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

        $this->connection->literalQuery($sql);
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
        return \implode(', ', $ret);
    }

    public function installRawFunction(RawFunction $func): void
    {
        $this->connection->literalQuery($func->completeFunction());
    }

    private function fieldToColumnDef(Field $field): string
    {
        $sql = $field->column . ' ' . $field->columnType->pgType();

        if (!$field->isNullable) {
            $sql .= ' NOT NULL';
        }

        if (!$field->default instanceof NoDefaultValue) {
            $sql .= match ($field->default->value) {
                true => ' DEFAULT true',
                false => ' DEFAULT false',
                default => ' DEFAULT ' . $field->default->value,
            };
        }

        return $sql;
    }
}
