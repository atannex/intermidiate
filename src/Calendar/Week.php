<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A row of seven days.
 *
 * @implements Arrayable<int, mixed>
 * @implements IteratorAggregate<int, Day>
 */
final class Week implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  list<Day|null>  $days  null cells when outside days are left out
     */
    public function __construct(
        /** ISO-8601 week number */
        public readonly int $number,
        private readonly array $days,
    ) {}

    /** @return Collection<int, Day|null> */
    public function days(): Collection
    {
        return new Collection($this->days);
    }

    public function current(): bool
    {
        foreach ($this->days as $day) {
            if ($day?->current) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->days);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->days);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['number' => $this->number, 'days' => array_map(fn (?Day $day) => $day?->toArray(), $this->days)];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
