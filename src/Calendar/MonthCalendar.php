<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use Atannex\Intermidiate\Support\Numeral;
use Atannex\Intermidiate\Support\Settings;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A month as a grid of weeks, padded with the neighbouring months' days so
 * every row has seven cells, plus the weekday headings in the right order.
 *
 *     @foreach ($calendar->weekdays() as $weekday) <th>{{ $weekday->short }}</th> @endforeach
 *     @foreach ($calendar->weeks() as $week)
 *         <tr> @foreach ($week as $day) <td @class(['muted' => ! $day->inMonth])>{{ $day->number }}</td> @endforeach </tr>
 *
 *     @endforeach
 *
 * @implements Arrayable<string, mixed>
 * @implements IteratorAggregate<int, Week>
 */
final class MonthCalendar implements Arrayable, IteratorAggregate, JsonSerializable
{
    private ?int $current = null;

    /** @var array<int, int> */
    private array $counts = [];

    private bool $tracksFilled = false;

    private ?Closure $link = null;

    private ?int $weekStartsOn = null;

    private ?string $locale = null;

    private bool $outside = true;

    public readonly CarbonImmutable $start;

    public function __construct(public readonly int $year, public readonly int $month)
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("A month runs from 1 to 12, got {$month}.");
        }

        $this->start = CarbonImmutable::create($year, $month, 1);
    }

    /** The day being viewed: 7, "07", a date in this month, or null. */
    public function current(int|string|DateTimeInterface|null $day): self
    {
        $this->current = match (true) {
            $day instanceof DateTimeInterface => $day->format('Y-m') === $this->start->format('Y-m') ? (int) $day->format('j') : null,
            $day === null, $day === '' => null,
            default => (int) $day,
        };

        return $this;
    }

    /**
     * Days that have content: a list (7, "07", "2026-03-07", dates) or a map
     * of day => count. Without this every day counts as filled.
     *
     * @param  iterable<mixed>|Arrayable<array-key, mixed>  $days
     */
    public function filled(iterable|Arrayable $days): self
    {
        $this->counts = Counts::normalize($days, 'Y-m', $this->start->format('Y-m'));
        $this->tracksFilled = true;

        return $this;
    }

    /**
     * The link for each day of this month; empty days get none unless $empty.
     *
     * @param  Closure(Day): ?string  $link
     */
    public function link(Closure $link, bool $empty = false): self
    {
        $this->link = $empty ? $link : fn (Day $day) => $day->filled ? $link($day) : null;

        return $this;
    }

    /** 0 (Sunday) to 6 (Saturday); defaults to config intermidiate.week_starts_on. */
    public function weekStartsOn(int $day): self
    {
        $this->weekStartsOn = (($day % 7) + 7) % 7;

        return $this;
    }

    /** Leave the neighbouring months' cells out (null cells instead). */
    public function withoutOutsideDays(): self
    {
        $this->outside = false;

        return $this;
    }

    public function locale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /** This month as a Month value (name, padded param, filled…). */
    public function month(): Month
    {
        return Calendar::year($this->year)
            ->current($this->month)
            ->locale($this->language())
            ->months()
            ->get($this->month - 1);
    }

    /** @return Collection<int, Weekday> */
    public function weekdays(): Collection
    {
        $first = $this->firstDay();
        $locale = $this->language();

        return (new Collection(range(0, 6)))->map(function (int $offset) use ($first, $locale) {
            $number = ($first + $offset) % 7;
            [$name, $short, $min] = Names::weekday($number, $locale);

            return new Weekday($number, $name, $short, $min, $number === 0 || $number === 6);
        });
    }

    /** @return Collection<int, Week> */
    public function weeks(): Collection
    {
        $first = $this->firstDay();
        $locale = $this->language();
        $today = $this->today();
        $cursor = $this->start->subDays(($this->start->dayOfWeek - $first + 7) % 7)->locale($locale);
        $end = $this->start->endOfMonth();
        $weeks = [];

        while ($cursor <= $end) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $days[] = $this->day($cursor, $locale, $today);
                // modify() and format('W') are native: Carbon's addDay() and isoWeek() cost many times more.
                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = new Week((int) $days[0]->date->format('W'), $days);
        }

        if (! $this->outside) {
            $weeks = array_map(fn (Week $week) => new Week($week->number, array_map(
                fn (Day $day) => $day->inMonth ? $day : null,
                $week->days()->all(),
            )), $weeks);
        }

        return new Collection($weeks);
    }

    /** Only this month's days, in order. @return Collection<int, Day> */
    public function days(): Collection
    {
        $locale = $this->language();
        $today = $this->today();
        $start = $this->start->locale($locale);

        return (new Collection(range(1, $this->start->daysInMonth)))->map(fn (int $day) => $this->day($start->setDay($day), $locale, $today));
    }

    public function previous(): self
    {
        $date = $this->start->subMonth();

        return Calendar::month((int) $date->format('Y'), (int) $date->format('n'));
    }

    public function next(): self
    {
        $date = $this->start->addMonth();

        return Calendar::month((int) $date->format('Y'), (int) $date->format('n'));
    }

    public function getIterator(): Traversable
    {
        return $this->weeks()->getIterator();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'month' => $this->month()->toArray(),
            'weekdays' => $this->weekdays()->map(fn (Weekday $weekday) => (array) $weekday)->all(),
            'weeks' => $this->weeks()->map(fn (Week $week) => $week->toArray())->all(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param  CarbonImmutable  $date  already in $locale */
    private function day(CarbonImmutable $date, string $locale, string $today): Day
    {
        // One native format() for everything Carbon's getters would work out one by one.
        [$year, $month, $number, $weekday] = array_map('intval', explode(' ', $date->format('Y n j w')));
        $inMonth = $year === $this->year && $month === $this->month;
        $count = $inMonth ? ($this->counts[$number] ?? 0) : 0;

        $day = new Day(
            $date,
            $number,
            Numeral::pad($number, 2),
            $inMonth,
            $inMonth && $number === $this->current,
            "{$year} {$month} {$number}" === $today,
            $date->isWeekend(),
            $inMonth && (! $this->tracksFilled || $count > 0),
            $count,
            null,
            Names::weekday($weekday, $locale)[0],
        );

        if ($this->link === null || ! $inMonth) {
            return $day;
        }

        return new Day($day->date, $day->number, $day->param, $day->inMonth, $day->current, $day->today, $day->weekend, $day->filled, $day->count, ($this->link)($day), $day->weekday);
    }

    /** Today in the grid's timezone, as day() formats dates, the way Carbon's isToday() compares them. */
    private function today(): string
    {
        return CarbonImmutable::now($this->start->getTimezone())->format('Y n j');
    }

    private function firstDay(): int
    {
        return $this->weekStartsOn ?? (((int) Settings::get('week_starts_on', 1) % 7) + 7) % 7;
    }

    private function language(): string
    {
        return $this->locale ?? Settings::locale();
    }
}
