<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One cell of a month grid. Days from the neighbouring months that fill the
 * first and last week have $inMonth false.
 *
 * @implements Arrayable<string, mixed>
 */
final class Day implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly int $number,
        /** zero-padded: "07" */
        public readonly string $param,
        public readonly bool $inMonth,
        public readonly bool $current,
        public readonly bool $today,
        public readonly bool $weekend,
        public readonly bool $filled,
        public readonly int $count,
        public readonly ?string $url,
        public readonly string $weekday,
    ) {}

    /** "2026-03-07" */
    public function key(): string
    {
        return $this->date->format('Y-m-d');
    }

    /** ['year' => 2026, 'month' => '03', 'day' => '07'], for route(). */
    public function routeParameters(): array
    {
        return ['year' => (int) $this->date->format('Y'), 'month' => $this->date->format('m'), 'day' => $this->param];
    }

    public function isPast(): bool
    {
        return $this->date->endOfDay()->isPast();
    }

    public function isFuture(): bool
    {
        return $this->date->isFuture();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date' => $this->key(),
            'number' => $this->number,
            'param' => $this->param,
            'in_month' => $this->inMonth,
            'current' => $this->current,
            'today' => $this->today,
            'weekend' => $this->weekend,
            'filled' => $this->filled,
            'count' => $this->count,
            'url' => $this->url,
            'weekday' => $this->weekday,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
