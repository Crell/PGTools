<?php

declare(strict_types=1);

namespace Crell\PGTools\Procedures;

use Crell\PGTools\StoredFunction;
use Crell\PGTools\StoredLanguage;

class FindPeople implements StoredFunction
{
    public function name(): string
    {
        return 'find_people';
    }

    public function returns(): string
    {
        return 'TABLE (created_stamp TIMESTAMPTZ, doc JSONB)';
    }

    public function language(): StoredLanguage
    {
        return StoredLanguage::PlPgSql;
    }

    public function parameters(): array
    {
        return ['name' => 'text'];
    }

    public function body(): string
    {
        return <<<BODY
BEGIN
    RETURN QUERY SELECT data.created_stamp, document 
    FROM data 
    WHERE cast(document->>'name' AS text) = name;
END;
BODY;

    }

}
