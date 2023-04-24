<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\StoredFunction;
use Crell\PGTools\StoredLanguage;

class EnforceTimestampLogic implements StoredFunction
{
    public function returns(): string
    {
        return 'trigger';
    }

    public function name(): string
    {
        return 'update_timestamps';
    }

    public function language(): StoredLanguage
    {
        return StoredLanguage::PlPgSql;
    }

    public function body(): string
    {
        return <<<FUNCTION
            BEGIN
                -- Force the created stamp to never change.
                IF TG_OP = 'UPDATE' THEN
                    NEW.created = OLD.created;
                END IF;

                NEW.modified = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
        FUNCTION;
    }

    public function parameters(): array
    {
        return [];
    }
}
