<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Navigation;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\View\ComponentAttributeBag;
use JsonSerializable;

/**
 * A menu entry whose active state the Menu has already decided.
 *
 *     <a href="{{ $item->url }}" aria-current="{{ $item->current() }}" class="{{ $item->classes('nav__link', 'is-active') }}">
 *
 * or, in one go: `<a {{ $item->attributes(['class' => 'nav__link']) }}>`.
 *
 * @implements Arrayable<string, mixed>
 */
final class Item implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string|Closure>  $patterns  route names, paths or closures that make it active
     * @param  array<string, mixed>  $meta
     * @param  list<self>  $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $url = null,
        public readonly array $patterns = [],
        public readonly ?string $icon = null,
        public readonly mixed $badge = null,
        public readonly array $meta = [],
        private readonly array $children = [],
        /** this is the page being viewed */
        public readonly bool $active = false,
        /** one of its children is */
        public readonly bool $trail = false,
    ) {}

    public static function make(string $key, string $label, ?string $url = null): self
    {
        return new self($key, $label, $url);
    }

    /**
     * Builds an item from ['key', 'label', 'url', 'active_on', 'icon', 'badge', 'meta', 'children'].
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $key = (string) ($row['key'] ?? $row['url'] ?? $row['label'] ?? '');

        return new self(
            $key,
            (string) ($row['label'] ?? $key),
            isset($row['url']) ? (string) $row['url'] : null,
            array_values(Arr::wrap($row['active_on'] ?? $row['activeOn'] ?? [])),
            $row['icon'] ?? null,
            $row['badge'] ?? null,
            (array) ($row['meta'] ?? []),
            array_values(array_map(
                fn ($child) => $child instanceof self ? $child : self::fromArray((array) $child),
                (array) ($row['children'] ?? []),
            )),
        );
    }

    /** Also active on these route names ("archive.*"), paths ("/archive/*") or closures. */
    public function activeOn(string|Closure ...$patterns): self
    {
        return $this->copy(patterns: [...$this->patterns, ...array_values($patterns)]);
    }

    public function icon(?string $icon): self
    {
        return $this->copy(icon: $icon);
    }

    public function badge(mixed $badge): self
    {
        return $this->copy(badge: $badge);
    }

    /** @param  array<string, mixed>  $meta */
    public function meta(array $meta): self
    {
        return $this->copy(meta: [...$this->meta, ...$meta]);
    }

    /** @return Collection<int, self> */
    public function children(): Collection
    {
        return new Collection($this->children);
    }

    /** @param  iterable<self|array<string, mixed>>  $children */
    public function withChildren(iterable $children): self
    {
        $list = [];

        foreach ($children as $child) {
            $list[] = $child instanceof self ? $child : self::fromArray((array) $child);
        }

        return $this->copy(children: $list);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** Active, or one of its children is. */
    public function open(): bool
    {
        return $this->active || $this->trail;
    }

    /** "page" when active, for aria-current; null otherwise (Blade then leaves it empty). */
    public function current(string $value = 'page'): ?string
    {
        return $this->active ? $value : null;
    }

    /** A meta value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->meta, $key, $default);
    }

    /**
     * Classes for this item's state.
     *
     * @param  string|array<int, string>  $base
     * @param  string|array<int, string>  $active  when active
     * @param  string|array<int, string>  $inactive  when not
     * @param  string|array<int, string>|null  $trail  when a child is active (default: same as active)
     */
    public function classes(string|array $base = '', string|array $active = 'is-active', string|array $inactive = '', string|array|null $trail = null): string
    {
        $state = match (true) {
            $this->active => $active,
            $this->trail => $trail ?? $active,
            default => $inactive,
        };

        return trim(implode(' ', array_filter([...Arr::wrap($base), ...Arr::wrap($state)])));
    }

    /**
     * href, aria-current and the state class merged into your attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attributes(array $attributes = [], string $activeClass = 'is-active'): ComponentAttributeBag
    {
        $class = trim(($attributes['class'] ?? '').' '.($this->open() ? $activeClass : ''));
        unset($attributes['class']);

        return new ComponentAttributeBag(array_filter([
            'href' => $this->url,
            ...$attributes,
            'class' => $class === '' ? null : $class,
            'aria-current' => $this->current(),
        ], fn ($value) => $value !== null));
    }

    /** @internal */
    public function withState(bool $active, bool $trail, array $children): self
    {
        return $this->copy(active: $active, trail: $trail, children: $children);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'meta' => $this->meta ?: null,
            'active' => $this->active,
            'trail' => $this->trail,
            'children' => $this->children === [] ? null : array_map(fn (self $child) => $child->toArray(), $this->children),
        ], fn ($value) => $value !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function copy(mixed ...$changes): self
    {
        return new self(...[
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
            'patterns' => $this->patterns,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'meta' => $this->meta,
            'children' => $this->children,
            'active' => $this->active,
            'trail' => $this->trail,
            ...$changes,
        ]);
    }
}
