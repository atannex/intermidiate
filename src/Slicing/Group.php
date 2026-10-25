<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Slicing;

use ArrayIterator;
use Atannex\Intermidiate\Support\Numeral;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use OutOfRangeException;
use Traversable;

/**
 * A named slice of a partition (the lead, the ledger…). It keeps each item's
 * number on the whole listing, so a ledger that starts at the fourth story of
 * page two prints "24", "25"… Loops over its items; unknown methods are
 * passed to the items collection.
 *
 * @mixin Collection<int, mixed>
 *
 * @implements Arrayable<int, mixed>
 * @implements IteratorAggregate<int, mixed>
 */
final class Group implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  list<array{int, mixed}>  $slots  [0-based position on the page, item]
     * @param  int  $base  number of the page's first item
     */
    public function __construct(
        public readonly string $name,
        private readonly array $slots,
        private readonly int $base = 1,
        private readonly int $width = 2,
    ) {}

    /** @return Collection<int, mixed> */
    public function items(): Collection
    {
        return new Collection(array_column($this->slots, 1));
    }

    /** @return Collection<int, Entry> */
    public function entries(): Collection
    {
        $count = count($this->slots);

        return (new Collection($this->slots))->values()->map(
            fn (array $slot, int $index) => new Entry($slot[1], $this->base + $slot[0], $index, $count, $this->width),
        );
    }

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback !== null) {
            return $this->items()->first($callback, $default);
        }

        return $this->slots === [] ? value($default) : $this->slots[0][1];
    }

    public function last(): mixed
    {
        return $this->slots === [] ? null : $this->slots[count($this->slots) - 1][1];
    }

    public function isEmpty(): bool
    {
        return $this->slots === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->slots !== [];
    }

    public function count(): int
    {
        return count($this->slots);
    }

    /** Number of the first item on the whole listing, or null when empty. */
    public function start(): ?int
    {
        return $this->slots === [] ? null : $this->base + $this->slots[0][0];
    }

    /** Number of the last item. */
    public function end(): ?int
    {
        return $this->slots === [] ? null : $this->base + $this->slots[count($this->slots) - 1][0];
    }

    /** Number of the item at a 0-based index of this group ($loop->index). */
    public function numberAt(int $index): int
    {
        $slot = $this->slots[$index] ?? throw new OutOfRangeException("Group \"{$this->name}\" has no item {$index}.");

        return $this->base + $slot[0];
    }

    /** The padded number of the item at an index: "07". */
    public function folio(int $index, ?int $width = null): string
    {
        return Numeral::pad($this->numberAt($index), $width ?? $this->width);
    }

    /** "04–12", or one number for a single item. */
    public function range(string $separator = '–'): string
    {
        if ($this->slots === []) {
            return '';
        }

        $start = Numeral::pad($this->start(), $this->width);
        $end = Numeral::pad($this->end(), $this->width);

        return $start === $end ? $start : $start.$separator.$end;
    }

    /**
     * Splits into $count columns of near-equal length, filled top to bottom.
     *
     * @return Collection<int, self>
     */
    public function columns(int $count): Collection
    {
        $count = max(1, $count);
        $size = (int) ceil(count($this->slots) / $count);

        return $this->split($size === 0 ? 1 : $size, $count);
    }

    /**
     * Groups of $size items.
     *
     * @return Collection<int, self>
     */
    public function chunk(int $size): Collection
    {
        return $this->split(max(1, $size));
    }

    /** A new group with only the items that pass the test; numbers are kept. */
    public function filter(callable $callback): self
    {
        return $this->with(array_values(array_filter($this->slots, fn (array $slot) => $callback($slot[1]))));
    }

    /** A new group with every item passed through the callback; numbers are kept. */
    public function map(callable $callback): self
    {
        return $this->with(array_map(fn (array $slot) => [$slot[0], $callback($slot[1])], $this->slots));
    }

    /** The first $count items, numbers kept. */
    public function take(int $count): self
    {
        return $this->with(array_slice($this->slots, 0, max(0, $count)));
    }

    /** Everything after the first $count items, numbers kept. */
    public function skip(int $count): self
    {
        return $this->with(array_slice($this->slots, max(0, $count)));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_column($this->slots, 1));
    }

    /** @return list<mixed> */
    public function toArray(): array
    {
        return $this->items()->toArray();
    }

    public function jsonSerialize(): mixed
    {
        return $this->items()->jsonSerialize();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->items()->{$method}(...$arguments);
    }

    /**
     * @param  list<array{int, mixed}>  $slots
     */
    private function with(array $slots): self
    {
        return new self($this->name, $slots, $this->base, $this->width);
    }

    /**
     * @return Collection<int, self>
     */
    private function split(int $size, ?int $count = null): Collection
    {
        $chunks = array_chunk($this->slots, $size);

        if ($count !== null) {
            $chunks = array_pad($chunks, $count, []);
        }

        return (new Collection($chunks))->map(fn (array $slots) => $this->with($slots));
    }
}
