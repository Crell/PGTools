<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\StoredLanguage;

class AddDocRevision implements \Crell\PGTools\StoredProcedure
{

    public function name(): string
    {
        return 'add_doc_revision';
    }

    public function language(): StoredLanguage
    {
        return StoredLanguage::PlPgSql;
    }

    public function parameters(): array
    {
        return [
            'uuid_in' => 'uuid',
            'revision_in' => 'uuid',
            'active' => 'boolean',
            'class' => 'varchar',
            'document_in' => 'JSONB',
        ];
    }

    public function body(): string
    {
        return <<<PROC
            BEGIN
            INSERT INTO document 
            (uuid, revision, latest, active, document, class) 
            VALUES (uuid_in, revision_in, true, active, document_in, class);

            UPDATE document SET latest=false 
                WHERE uuid=uuid_in
                    AND revision <> revision_in;
            
            IF active THEN
                UPDATE document SET active=false 
                    WHERE uuid=uuid_in
                        AND revision <> revision_in;
            END IF;

            END;
        PROC;
    }
}
