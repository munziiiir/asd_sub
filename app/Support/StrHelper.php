<?php

namespace App\Support;

class StrHelper
{
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $suffixLength = mb_strlen($end);

        if ($suffixLength >= $limit) {
            return mb_substr($value, 0, $limit);
        }

        return mb_substr($value, 0, $limit - $suffixLength) . $end;
    }
}
