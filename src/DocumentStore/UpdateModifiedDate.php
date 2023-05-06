<?php

declare(strict_types=1);

namespace Crell\PGTools\DocumentStore;

use Crell\PGTools\RawFunction;
use Crell\PGTools\RawTrigger;
use Crell\PGTools\StoredFunction;

class UpdateModifiedDate implements RawTrigger
{
    private StoredFunction $func;

    public function __construct()
    {
        $this->func = new EnforceTimestampLogic();
    }

    public function completeTrigger(): string
    {
        return <<<TRIGGER
            CREATE TRIGGER update_timestamps
              BEFORE INSERT OR UPDATE ON document
              FOR EACH ROW  
            EXECUTE PROCEDURE {$this->func->name()}();
        TRIGGER;
    }

    public function triggerFunction(): StoredFunction|RawFunction
    {
        return $this->func;
    }
}
