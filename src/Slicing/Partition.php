<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Slicing;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Support\Settings;
use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Cuts a listing into named groups, in order:
 *
 *     Partition::of($posts)              // a paginator, collection or array
 *         ->take('lead', 1, fn ($post) => $post->hasMedia('image'))
 *         ->take('beside', 2)
 *         ->rest('ledger')
 *         ->atLeast(3)                   // fewer than 3? everything goes to the ledger
 *         ->when($posts->onFirstPage())  // the lead layout only opens page one
 *         ->get();
 *
 * Every item keeps its number on the whole listing (a paginator's page two
 * starts at its firstItem()), so folios stay right however the page is cut.
 */
final class Partition
{
    /** @var list<array{string, ?int, ?Closure}> [name, count (null = the rest), test] */
    private array $steps = [];

    /** @var list<Closure(Collection<int, mixed>): bool> */
    private array $conditions = [];

    private ?string $fallback = null;

    private ?int $start = null;

    private ?int $width = null;

    private Closure|string|null $unique = null;

    private bool $distinct = false;

    /**
     * @param  iterable<mixed>|Paginator|CursorPaginator  $items
     */
    private function __construct(private readonly iterable|Paginator|CursorPaginator $items) {}

    /**
     * @param  iterable<mixed>|Paginator|CursorPaginator  $items
     */
    public static function of(iterable|Paginator|CursorPaginator $items): self
    {
        return new self($items);
    }

    /**
     * The next $count items into $name. With a test, the first $count items
     * that pass it are taken wherever they sit, and the rest keep their order.
     */
    public function take(string $name, int $count, ?callable $where = null): self
    {
        $this->guard($name);
        $this->steps[] = [$name, max(0, $count), $where === null ? null : Closure::fromCallable($where)];

        return $this;
    }

    /** Whatever is left into $name. */
    public function rest(string $name): self
    {
        $this->guard($name);
        $this->steps[] = [$name, null, null];

        return $this;
    }

    /**
     * Use the layout only when there are at least $minimum items; otherwise
     * every item goes to $otherwise (by default the rest() group).
     */
    public function atLeast(int $minimum, ?string $otherwise = null): self
    {
        return $this->when(fn (Collection $items) => $items->count() >= $minimum, $otherwise);
    }

    /**
     * Use the layout only when the condition holds; otherwise every item goes
     * to $otherwise (by default the rest() group).
     *
     * @param  bool|callable(Collection<int, mixed>): bool  $condition
     */
    public function when(bool|callable $condition, ?string $otherwise = null): self
    {
        $this->conditions[] = is_bool($condition) ? fn () => $condition : Closure::fromCallable($condition);
        $this->fallback = $otherwise ?? $this->fallback;

        return $this;
    }

    /** Drop repeated items first, by key, callback or (no argument) identity. */
    public function unique(callable|string|null $key = null): self
    {
        $this->distinct = true;
        $this->unique = is_callable($key) && ! is_string($key) ? Closure::fromCallable($key) : $key;

        return $this;
    }

    /** Number the first item $number instead of reading it from the paginator. */
    public function startAt(int $number): self
    {
        $this->start = $number;

        return $this;
    }

    /** Folio width for this partition: 3 prints "007". */
    public function pad(int $width): self
    {
        $this->width = max(1, $width);

        return $this;
    }

    public function get(): Partitioned
    {
        $items = $this->collect();
        $base = $this->start ?? $this->base();
        $width = $this->width ?? Settings::pad();
        $pool = [];

        foreach ($items->values() as $position => $item) {
            $pool[$position] = $item;
        }

        $applies = true;

        foreach ($this->conditions as $condition) {
            if (! $condition($items)) {
                $applies = false;
                break;
            }
        }

        $groups = [];

        if (! $applies) {
            $target = $this->fallbackGroup();

            foreach ($this->steps as [$name]) {
                $groups[$name] = new Group($name, $name === $target ? self::slots($pool) : [], $base, $width);
            }

            return new Partitioned($groups, new Group('remainder', [], $base, $width), false, $this->source());
        }

        foreach ($this->steps as [$name, $count, $where]) {
            $picked = [];

            foreach ($pool as $position => $item) {
                if ($count !== null && count($picked) >= $count) {
                    break;
                }

                if ($where === null || $where($item)) {
                    $picked[$position] = $item;
                }
            }

            $pool = array_diff_key($pool, $picked);
            $groups[$name] = new Group($name, self::slots($picked), $base, $width);
        }

        return new Partitioned($groups, new Group('remainder', self::slots($pool), $base, $width), true, $this->source());
    }

    /** @return Collection<int, mixed> */
    private function collect(): Collection
    {
        $items = match (true) {
            $this->items instanceof Paginator, $this->items instanceof CursorPaginator => new Collection($this->items->items()),
            $this->items instanceof Collection => $this->items->values(),
            default => new Collection($this->items),
        };

        if (! $this->distinct) {
            return $items->values();
        }

        return ($this->unique === null
            ? $items->unique(fn ($item) => is_object($item) ? spl_object_id($item) : $item)
            : $items->unique($this->unique))->values();
    }

    private function base(): int
    {
        return $this->items instanceof Paginator ? (int) ($this->items->firstItem() ?? 1) : 1;
    }

    private function source(): Paginator|CursorPaginator|null
    {
        return $this->items instanceof Paginator || $this->items instanceof CursorPaginator ? $this->items : null;
    }

    private function fallbackGroup(): string
    {
        if ($this->fallback !== null) {
            return $this->fallback;
        }

        foreach ($this->steps as [$name, $count]) {
            if ($count === null) {
                return $name;
            }
        }

        throw new PresenterException('The partition fell back but has no rest() group to fall back to. Add one, or name it: ->atLeast(3, otherwise: \'ledger\').');
    }

    private function guard(string $name): void
    {
        foreach ($this->steps as [$existing]) {
            if ($existing === $name) {
                throw new PresenterException("The partition already has a \"{$name}\" group.");
            }
        }

        if ($name === 'remainder') {
            throw new PresenterException('"remainder" is reserved for items no group took; pick another name.');
        }
    }

    /**
     * @param  array<int, mixed>  $items  keyed by position
     * @return list<array{int, mixed}>
     */
    private static function slots(array $items): array
    {
        $slots = [];

        foreach ($items as $position => $item) {
            $slots[] = [$position, $item];
        }

        return $slots;
    }
}
