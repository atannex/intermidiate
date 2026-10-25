<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One month of a year calendar.
 *
 *     <a href="{{ $month->url }}" @class(['is-current' => $month->current, 'is-empty' => ! $month->filled])>
 *         {{ $month->short }}
 *     </a>
 *
 * @implements Arrayable<string, mixed>
 */
final class Month implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly int $year,
        public readonly int $number,
        /** zero-padded for routes and query strings: "03" */
        public readonly string $param,
        public readonly string $name,
        public readonly string $short,
        public readonly bool $current,
        public readonly bool $filled,
        public readonly int $count,
        public readonly ?string $url,
        public readonly CarbonImmutable $start,
    ) {}

    /** "2026-03" */
    public function key(): string
    {
        return $this->year.'-'.$this->param;
    }

    /** "March 2026" */
    public function label(): string
    {
        return $this->name.' '.$this->year;
    }

    /** ['year' => 2026, 'month' => '03'], for route(). */
    public function routeParameters(): array
    {
        return ['year' => $this->year, 'month' => $this->param];
    }

    public function end(): CarbonImmutable
    {
        return $this->start->endOfMonth();
    }

    public function days(): int
    {
        return $this->start->daysInMonth;
    }

    public function isPast(): bool
    {
        return $this->end()->isPast();
    }

    public function isFuture(): bool
    {
        return $this->start->isFuture();
    }

    public function isThisMonth(): bool
    {
        return $this->start->isSameMonth(CarbonImmutable::now());
    }

    /** The day grid of this month. */
    public function calendar(): MonthCalendar
    {
        return Calendar::month($this->year, $this->number);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'number' => $this->number,
            'param' => $this->param,
            'key' => $this->key(),
            'name' => $this->name,
            'short' => $this->short,
            'current' => $this->current,
            'filled' => $this->filled,
            'count' => $this->count,
            'url' => $this->url,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
