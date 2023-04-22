<?php

declare(strict_types=1);

namespace Crell\PGTools\Procedures;

use Crell\PGTools\StoredFunction;
use Crell\PGTools\StoredLanguage;

class HasPerson implements StoredFunction
{

    public function returns(): string
    {
        return 'bool';
    }

    public function name(): string
    {
        return 'has_person';
    }

    public function language(): StoredLanguage
    {
        return StoredLanguage::PlPgSql;
    }

    public function body(): string
    {
        return <<<STATEMENT
DECLARE record_count INTEGER;
BEGIN
  SELECT count(*) INTO record_count
  FROM data
  WHERE cast(document->>'name' AS text) = $1;
  RETURN record_count;
END;
STATEMENT;
    }

    public function parameters(): array
    {
        return ['name' => 'text'];
    }

    public function completeFunction(): string
    {
        return <<<STATEMENT
CREATE OR REPLACE FUNCTION has_person(name text) RETURNS bool
LANGUAGE PLPGSQL
AS $$
  DECLARE record_count INTEGER;
BEGIN
  SELECT count(*) INTO record_count
  FROM data
  WHERE cast(document->>'name' AS text) = $1;
  RETURN record_count;
END;
$$
STATEMENT;
    }
}
