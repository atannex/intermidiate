<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use Carbon\CarbonImmutable;

/**
 * @internal Month and weekday names per locale. Carbon translates every
 * format call, and a month grid asks for the same seven names five times,
 * so each locale is translated once.
 */
final class Names
{
    /** @var array<string, array<int, array{string, string}>> locale => month 1-12 => [name, short] */
    private static array $months = [];

    /** @var array<string, array<int, array{string, string, string}>> locale => weekday 0-6 (Sunday first) => [name, short, min] */
    private static array $weekdays = [];

    /** @return array{string, string} "March", "Mar" */
    public static function month(int $number, string $locale): array
    {
        if (! isset(self::$months[$locale])) {
            $january = CarbonImmutable::create(2024, 1, 1)->locale($locale);

            foreach (range(1, 12) as $month) {
                $date = $january->setDate(2024, $month, 1);
                self::$months[$locale][$month] = [$date->translatedFormat('F'), $date->translatedFormat('M')];
            }
        }

        return self::$months[$locale][$number];
    }

    /** @return array{string, string, string} "Monday", "Mon", "Mo" */
    public static function weekday(int $number, string $locale): array
    {
        if (! isset(self::$weekdays[$locale])) {
            // 7 January 2024 was a Sunday.
            $sunday = CarbonImmutable::create(2024, 1, 7)->locale($locale);

            foreach (range(0, 6) as $day) {
                $date = $sunday->addDays($day);
                self::$weekdays[$locale][$day] = [$date->translatedFormat('l'), $date->translatedFormat('D'), $date->isoFormat('dd')];
            }
        }

        return self::$weekdays[$locale][$number];
    }

    public static function flush(): void
    {
        self::$months = [];
        self::$weekdays = [];
    }
}
