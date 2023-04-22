<?php

declare(strict_types=1);

namespace Crell\PGTools;

class HasPersonRaw implements RawFunction
{
    public function completeFunction(): string
    {
        return <<<STATEMENT
CREATE OR REPLACE FUNCTION has_person_raw(name text) RETURNS bool
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
