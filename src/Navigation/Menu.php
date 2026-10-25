<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Navigation;

use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A list of links whose active item is decided in PHP, from the request:
 *
 *     Menu::make()
 *         ->add('home', __('Home'), route('home'))
 *         ->add('archive', __('Archive'), route('archive.index'), activeOn: 'archive.*')
 *         ->sections();     // "/archive" stays active on "/archive/2026/03"
 *
 * Only the most specific match is active (an exact URL beats a pattern,
 * which beats a section prefix); multiple() allows several. Parents of the
 * active item are marked as its trail.
 *
 * @implements Arrayable<int, mixed>
 * @implements IteratorAggregate<int, Item>
 */
final class Menu implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Item> */
    private array $items = [];

    private ?string $forced = null;

    private bool $multiple = false;

    private bool $sections = false;

    private ?Request $request = null;

    /** @var list<Item>|null */
    private ?array $resolved = null;

    /**
     * @param  iterable<Item|array<string, mixed>>  $items
     */
    public static function make(iterable $items = []): self
    {
        $menu = new self;

        foreach ($items as $item) {
            $menu->push($item instanceof Item ? $item : Item::fromArray((array) $item));
        }

        return $menu;
    }

    /**
     * Maps rows (a config array, models…) to items.
     *
     * @template T
     *
     * @param  iterable<array-key, T>  $rows
     * @param  callable(T, array-key): (Item|array<string, mixed>)  $map
     */
    public static function from(iterable $rows, callable $map): self
    {
        $items = [];

        foreach ($rows as $key => $row) {
            $items[] = $map($row, $key);
        }

        return self::make($items);
    }

    /**
     * @param  string|Closure|list<string|Closure>|null  $activeOn
     * @param  array<string, mixed>  $meta
     * @param  iterable<Item|array<string, mixed>>  $children
     */
    public function add(
        string $key,
        string $label,
        ?string $url = null,
        string|Closure|array|null $activeOn = null,
        ?string $icon = null,
        mixed $badge = null,
        array $meta = [],
        iterable $children = [],
    ): self {
        $item = Item::make($key, $label, $url)
            ->activeOn(...($activeOn === null ? [] : (is_array($activeOn) ? $activeOn : [$activeOn])))
            ->icon($icon)
            ->badge($badge)
            ->meta($meta)
            ->withChildren($children);

        return $this->push($item);
    }

    public function push(Item ...$items): self
    {
        array_push($this->items, ...$items);
        $this->resolved = null;

        return $this;
    }

    /** Make this key the active one, whatever the request says. */
    public function force(?string $key): self
    {
        $this->forced = $key;
        $this->resolved = null;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;
        $this->resolved = null;

        return $this;
    }

    /** Items also match the pages below their URL. */
    public function sections(bool $sections = true): self
    {
        $this->sections = $sections;
        $this->resolved = null;

        return $this;
    }

    /** Decide against this request instead of the current one. */
    public function for(Request $request): self
    {
        $this->request = $request;
        $this->resolved = null;

        return $this;
    }

    /** @return Collection<int, Item> */
    public function items(): Collection
    {
        return new Collection($this->resolve());
    }

    /** The deepest active item. */
    public function active(): ?Item
    {
        $found = null;

        $this->walk($this->resolve(), function (Item $item) use (&$found) {
            if ($item->active) {
                $found = $item;
            }
        });

        return $found;
    }

    public function activeKey(): ?string
    {
        return $this->active()?->key;
    }

    /**
     * The path from the top level down to the active item (breadcrumbs).
     *
     * @return Collection<int, Item>
     */
    public function trail(): Collection
    {
        $trail = [];
        $level = $this->resolve();

        while (($open = (new Collection($level))->first(fn (Item $item) => $item->open())) !== null) {
            $trail[] = $open;

            if ($open->active && ! $open->trail) {
                break;
            }

            $level = $open->children()->all();
        }

        return new Collection($trail);
    }

    public function find(string $key): ?Item
    {
        $found = null;

        $this->walk($this->resolve(), function (Item $item) use ($key, &$found) {
            $found ??= $item->key === $key ? $item : null;
        });

        return $found;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->resolve());
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (Item $item) => $item->toArray(), $this->resolve());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<Item> */
    private function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if ($this->forced !== null) {
            $chosen = [$this->forced => true];

            if (! $this->contains($this->items, $this->forced)) {
                throw new InvalidArgumentException("The menu has no item \"{$this->forced}\" to make active.");
            }
        } else {
            $chosen = $this->match();
        }

        return $this->resolved = $this->apply($this->items, $chosen);
    }

    /** @return array<string, true> keys of the items to mark active */
    private function match(): array
    {
        $request = $this->request ?? $this->currentRequest();

        if ($request === null) {
            return [];
        }

        $active = new Active($request, $this->sections);
        $scores = [];

        $this->walk($this->items, function (Item $item) use ($active, &$scores) {
            if (($score = $active->score($item)) > 0 && ! isset($scores[$item->key])) {
                $scores[$item->key] = $score;
            }
        });

        if ($scores === [] || $this->multiple) {
            return array_map(fn () => true, $scores);
        }

        $best = max($scores);

        return [array_search($best, $scores, true) => true];
    }

    /**
     * @param  list<Item>  $items
     * @param  array<string, true>  $chosen
     * @return list<Item>
     */
    private function apply(array $items, array $chosen): array
    {
        return array_map(function (Item $item) use ($chosen) {
            $children = $this->apply($item->children()->all(), $chosen);
            $trail = (new Collection($children))->contains(fn (Item $child) => $child->open());

            return $item->withState(isset($chosen[$item->key]), $trail, $children);
        }, $items);
    }

    /**
     * @param  list<Item>  $items
     */
    private function walk(array $items, Closure $callback): void
    {
        foreach ($items as $item) {
            $callback($item);
            $this->walk($item->children()->all(), $callback);
        }
    }

    /**
     * @param  list<Item>  $items
     */
    private function contains(array $items, string $key): bool
    {
        $found = false;

        $this->walk($items, function (Item $item) use ($key, &$found) {
            $found = $found || $item->key === $key;
        });

        return $found;
    }

    private function currentRequest(): ?Request
    {
        $container = Container::getInstance();

        return $container->bound('request') ? $container->make('request') : null;
    }
}
