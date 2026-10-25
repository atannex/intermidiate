<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @internal Turns "which months/days have content" in any common shape into
 * [number => count]: a list (3, "03", "2026-03", a date) or a map
 * (number => count).
 */
final class Counts
{
    /**
     * @param  iterable<mixed>|Arrayable<array-key, mixed>  $values
     * @param  string  $scope  "Y" for months of a year, "Y-m" for days of a month
     * @return array<int, int>
     */
    public static function normalize(iterable|Arrayable $values, string $scope, string $prefix): array
    {
        $values = $values instanceof Arrayable ? $values->toArray() : (is_array($values) ? $values : iterator_to_array($values));
        $counts = [];

        if (! array_is_list($values)) {
            foreach ($values as $key => $count) {
                if (($number = self::number($key, $scope, $prefix)) !== null) {
                    $counts[$number] = ($counts[$number] ?? 0) + (int) $count;
                }
            }

            return $counts;
        }

        foreach ($values as $value) {
            if (($number = self::number($value, $scope, $prefix)) !== null) {
                $counts[$number] = ($counts[$number] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private static function number(mixed $value, string $scope, string $prefix): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($scope) === $prefix ? (int) $value->format($scope === 'Y' ? 'n' : 'j') : null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        // "2026-03" for months, "2026-03-07" (or a datetime) for days.
        if (preg_match($scope === 'Y' ? '/^(\d{4})-(\d{1,2})/' : '/^(\d{4}-\d{2})-(\d{1,2})/', $value, $match)) {
            return $match[1] === $prefix ? (int) $match[2] : null;
        }

        return null;
    }
}
