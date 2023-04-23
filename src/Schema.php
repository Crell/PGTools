<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\PGTools\Attributes\Field;
use Crell\PGTools\Attributes\Table;
use function Crell\fp\prop;

class Schema
{
    public function __construct(
        readonly private Connection $connection,
        readonly private ClassAnalyzer $analyzer = new MemoryCacheAnalyzer(new Analyzer()),
    ) {}

    /**
     *
     *
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
            $name = \implode('_', array_map(prop('name'), $idFields));
            $columns = \implode(', ', array_map(prop('column'), $idFields));
            $columnSql .= sprintf(",\nCONSTRAINT %s PRIMARY KEY(%s)", $name, $columns);
        }

        $sql = "CREATE TABLE $tableDef->name ($columnSql)";

        $this->connection->literalQuery($sql);
    }

    private function fieldToColumnDef(Field $field): string
    {
        $sql = $field->column . ' ' . $field->columnType->pgType();

        if (!$field->isNullable) {
            $sql .= ' NOT NULL';
        }

        if (!$field->default instanceof NoDefaultValue) {
            $sql .= ' DEFAULT ' . $field->default->value;
        }

        return $sql;
    }
}
