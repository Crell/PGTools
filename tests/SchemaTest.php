<?php

declare(strict_types=1);

namespace Crell\PGTools;

use Crell\PGTools\Tables\ArrayExample;
use Crell\PGTools\Tables\Data;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    use ConnectionUtils;

    public function setUp(): void
    {
        $this->initConnection();

        $this->connection->literalQuery("DROP TABLE IF EXISTS data");
        $this->connection->literalQuery("DROP TABLE IF EXISTS ArrayExample");
    }

    #[Test]
    public function table_creation_works(): void
    {
        $schema = $this->connection->schema();
        $schema->ensureTable(Data::class);

        $stmt = $this->connection->prepare('INSERT INTO data (document) VALUES (:document)');

        $stmt->execute([
            ':document' => '{"name": "Larry"}',
        ]);

        $found = $this->connection->literalQuery("SELECT count(*) 
          FROM data
          WHERE cast(document->>'name' AS text) = 'Larry'")->fetchColumn();

        self::assertSame(1, $found);
    }

    #[Test]
    public function array_columns_work(): void
    {
        $schema = $this->connection->schema();

        $schema->ensureTable(ArrayExample::class);

        $stmt = $this->connection->prepare('INSERT INTO ArrayExample (series, characters) VALUES (:series, :characters)');

        $stmt->execute([
            'series' => 'Star Wars',
            'characters' => '{"Luke", "Leia", "Han"}',
        ]);
        $stmt->execute([
            'series' => 'Voyager',
            'characters' => '{"Janeway", "Chakotay", "B\'elana"}',
        ]);

        $records = $this->connection->literalQuery("SELECT * FROM ArrayExample WHERE series='Star Wars'")->fetchAll();

        self::assertCount(1, $records);

        $chars = $this->decodePgArray($records[0]['characters']);
        self::assertCount(3, $chars);
        self::assertSame(['Luke', 'Leia', 'Han'], $chars);
    }

    /**
     * Shamelessly borrowed from https://stackoverflow.com/questions/3068683/convert-postgresql-array-to-php-array
     *
     * @todo This clearly belongs in a real utility, not a test, but not sure where.
     *
     * @param string $s
     * @param int $start
     * @param $end
     * @return ?array
     */
    function decodePgArray(string $s, int $start = 0, &$end = null): ?array
    {
        if (empty($s) || $s[0] !== '{') return null;
        $return = array();
        $string = false;
        $quote='';
        $len = strlen($s);
        $v = '';
        for ($i = $start + 1; $i < $len; $i++) {
            $ch = $s[$i];

            if (!$string && $ch === '}') {
                if ($v !== '' || !empty($return)) {
                    $return[] = $v;
                }
                $end = $i;
                break;
            } elseif (!$string && $ch === '{') {
                $v = $this->decodePgArray($s, $i, $i);
            } elseif (!$string && $ch === ','){
                $return[] = $v;
                $v = '';
            } elseif (!$string && ($ch === '"' || $ch === "'")) {
                $string = true;
                $quote = $ch;
            } elseif ($string && $ch === $quote && $s[$i - 1] === "\\") {
                $v = substr($v, 0, -1) . $ch;
            } elseif ($string && $ch === $quote && $s[$i - 1] !== "\\") {
                $string = false;
            } else {
                $v .= $ch;
            }
        }

        return $return;
    }
}
