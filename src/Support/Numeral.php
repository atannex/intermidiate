<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use InvalidArgumentException;

/**
 * Numbers the way editorial layouts print them: folios ("03"), counters
 * ("03 / 12"), roman numerals and bar widths. Ordinals and short counts are Laravel's Number::ordinal() and Number::abbreviate().
 */
final class Numeral
{
    /** "3" → "03". Null and "" stay empty. Width defaults to config intermidiate.pad. */
    public static function pad(int|string|null $number, ?int $width = null): string
    {
        if ($number === null || $number === '') {
            return '';
        }

        $number = (string) $number;
        $negative = str_starts_with($number, '-');
        $digits = ltrim($number, '-');

        return ($negative ? '-' : '').str_pad($digits, $width ?? Settings::pad(), '0', STR_PAD_LEFT);
    }

    /** 3 of 12 → "03 / 12". */
    public static function fraction(int $number, int $total, ?int $width = null, string $separator = ' / '): string
    {
        $width ??= max(Settings::pad(), strlen((string) $total));

        return self::pad($number, $width).$separator.self::pad($total, $width);
    }

    /** 2026 → "MMXXVI". */
    public static function roman(int $number): string
    {
        if ($number < 1 || $number > 3999) {
            throw new InvalidArgumentException("Roman numerals run from 1 to 3999, got {$number}.");
        }

        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $roman = '';

        foreach ($map as $symbol => $value) {
            $roman .= str_repeat($symbol, intdiv($number, $value));
            $number %= $value;
        }

        return $roman;
    }

    /** Share of a whole in percent; 0 when the whole is 0. */
    public static function percent(int|float $part, int|float $whole, int $precision = 0): int|float
    {
        if ($whole == 0) {
            return 0;
        }

        $percent = round(100 * $part / $whole, $precision);

        return $precision === 0 ? (int) $percent : $percent;
    }

    /**
     * A CSS width for a bar against the largest value: 30 of 120 → "25%".
     * A non-zero value never drops below $minimum so it stays visible.
     */
    public static function bar(int|float $value, int|float $max, int $minimum = 0): string
    {
        $percent = self::percent($value, $max);

        return ($value > 0 ? max($minimum, $percent) : 0).'%';
    }
}
