<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use DateTimeInterface;

/**
 * Calendars ready for markup: zero-padded parameters, translated names,
 * filled and current flags and links, all decided before the view renders.
 *
 *     Calendar::year(2026)->current(3)->filled(['01', '03'])
 *         ->link(fn (Month $month) => route('archive.month', $month->routeParameters()))
 *         ->months();
 *
 *     Calendar::month(2026, 3)->filled($days)->weeks();
 */
final class Calendar
{
    public static function year(int|string $year): YearCalendar
    {
        return new YearCalendar((int) $year);
    }

    public static function month(int|string $year, int|string $month): MonthCalendar
    {
        return new MonthCalendar((int) $year, (int) $month);
    }

    /** The month calendar a date falls in, with that day current. */
    public static function at(DateTimeInterface $date): MonthCalendar
    {
        return self::month((int) $date->format('Y'), (int) $date->format('n'))->current((int) $date->format('j'));
    }
}
