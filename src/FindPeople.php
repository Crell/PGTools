<?php

declare(strict_types=1);

namespace Crell\PGTools;

class FindPeople implements StoredFunction
{
    public function name(): string
    {
        return 'find_people';
    }

    public function returns(): string
    {
        return 'TABLE (created TIMESTAMPTZ, doc JSONB)';
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
    RETURN QUERY SELECT created_stamp, document 
    FROM data 
    WHERE cast(document->>'name' AS text) = $1;
END;
BODY;

    }

}
