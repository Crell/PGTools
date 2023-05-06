<?php

declare(strict_types=1);

namespace Crell\PGTools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class SequenceField implements ColumnType, SelfDecodingColumn
{
    /**
     * @param string $arrayType
     *   Elements in this array are objects of this type.
     */
    public function __construct(
        public readonly string $arrayType,
    ) {}

    public function pgType(): string
    {
        // Per https://www.postgresql.org/docs/15/arrays.html
        // Any depth or size of array specified has no effect and is ignored,
        // so we don't need to bother with it.
        return $this->arrayType . '[]';
    }

    /**
     * @param string $value
     * @return array<string, mixed>
     */
    public function decode(bool|int|string $value): array
    {
        return $this->decodePgArray($value);
    }

    /**
     * Shamelessly borrowed from https://stackoverflow.com/questions/3068683/convert-postgresql-array-to-php-array
     *
     * @todo It may also be better to use the JSON track suggested in the above thread.
     *
     * @param string $s
     * @param int $start
     * @param $end
     * @return ?array
     */
    private function decodePgArray(string $s, int $start = 0, &$end = null): ?array
    {
        if (empty($s) || $s[0] !== '{') {
            return null;
        }
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
            }

            if (!$string && $ch === '{') {
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
