<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Navigation;

use ArrayIterator;
use BackedEnum;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Tabs driven by a query parameter (?sort=popular): the selected tab is
 * validated against the options (anything else falls back to the default),
 * and every tab links to the current URL with its value and without ?page.
 *
 *     $tabs = Tabs::query('sort', ['latest' => __('Latest'), 'popular' => __('Most read')]);
 *     $tabs = Tabs::query('sort', SortOrder::class);    // a backed enum; selected() is the case
 *
 * @implements Arrayable<int, mixed>
 * @implements IteratorAggregate<int, Item>
 */
final class Tabs implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<string, string>  $options  value => label
     * @param  class-string<BackedEnum>|null  $enum
     */
    private function __construct(
        public readonly string $parameter,
        private readonly array $options,
        private readonly string $value,
        private readonly string $default,
        private readonly ?string $enum,
        private readonly Menu $menu,
    ) {}

    /**
     * @param  array<int|string, string>|class-string<BackedEnum>  $options  [value => label], [value, …] or an enum class
     */
    public static function query(string $parameter, array|string $options, string|BackedEnum|null $default = null, ?Request $request = null, bool $keepPage = false): self
    {
        $request ??= Container::getInstance()->make('request');
        [$options, $enum] = self::options($options);

        if ($options === []) {
            throw new InvalidArgumentException("Tabs for \"{$parameter}\" need at least one option.");
        }

        $default = $default instanceof BackedEnum ? (string) $default->value : ($default ?? (string) array_key_first($options));
        $asked = $request->query($parameter);
        $value = is_string($asked) && array_key_exists($asked, $options) ? $asked : $default;

        $menu = Menu::make();

        foreach ($options as $key => $label) {
            $query = [$parameter => $key === $default ? null : $key];

            if (! $keepPage) {
                $query['page'] = null;
            }

            $menu->push(Item::make((string) $key, $label, $request->fullUrlWithQuery($query)));
        }

        return new self($parameter, $options, $value, $default, $enum, $menu->force($value));
    }

    /** The selected value, or the enum case when the options came from an enum. */
    public function selected(): string|BackedEnum
    {
        if ($this->enum === null) {
            return $this->value;
        }

        $enum = $this->enum;

        return $enum::tryFrom($this->value) ?? $enum::from((int) $this->value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->options[$this->value];
    }

    public function isDefault(): bool
    {
        return $this->value === $this->default;
    }

    public function is(string|BackedEnum $value): bool
    {
        return $this->value === ($value instanceof BackedEnum ? (string) $value->value : $value);
    }

    public function menu(): Menu
    {
        return $this->menu;
    }

    /** @return Collection<int, Item> */
    public function items(): Collection
    {
        return $this->menu->items();
    }

    public function count(): int
    {
        return count($this->options);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->menu->items()->all());
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->menu->toArray();
    }

    public function jsonSerialize(): array
    {
        return ['parameter' => $this->parameter, 'selected' => $this->value, 'tabs' => $this->toArray()];
    }

    /**
     * @param  array<int|string, string>|string  $options
     * @return array{array<string, string>, ?string}
     */
    private static function options(array|string $options): array
    {
        if (is_string($options)) {
            if (! is_subclass_of($options, BackedEnum::class)) {
                throw new InvalidArgumentException("Tab options must be an array or a backed enum, got {$options}.");
            }

            $list = [];

            foreach ($options::cases() as $case) {
                $list[(string) $case->value] = method_exists($case, 'label') ? (string) $case->label() : Str::headline($case->name);
            }

            return [$list, $options];
        }

        if (array_is_list($options)) {
            $list = [];

            foreach ($options as $value) {
                $list[(string) $value] = Str::headline((string) $value);
            }

            return [$list, null];
        }

        $list = [];

        foreach ($options as $value => $label) {
            $list[(string) $value] = (string) $label;
        }

        return [$list, null];
    }
}
