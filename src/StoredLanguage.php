<?php
declare(strict_types=1);

namespace Crell\PGTools;

enum StoredLanguage: string
{
    case Sql = 'SQL';
    case PlPgSql = 'plpgsql';
}
