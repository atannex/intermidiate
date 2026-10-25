<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

/** @internal "Did you mean …?" for error messages. */
final class Suggest
{
    /**
     * @param  iterable<int|string, string>  $candidates
     */
    public static function closest(string $needle, iterable $candidates): ?string
    {
        $best = null;
        $distance = PHP_INT_MAX;
        $needle = strtolower($needle);

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            $score = levenshtein($needle, strtolower($candidate));

            if ($score < $distance) {
                [$best, $distance] = [$candidate, $score];
            }
        }

        return $best !== null && $distance <= max(2, intdiv(strlen($needle), 3)) ? $best : null;
    }

    /**
     * @param  iterable<int|string, string>  $candidates
     */
    public static function hint(string $needle, iterable $candidates): string
    {
        $closest = self::closest($needle, $candidates);

        return $closest === null ? '' : " Did you mean \"{$closest}\"?";
    }
}
