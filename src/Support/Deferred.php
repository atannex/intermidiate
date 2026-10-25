<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Htmlable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * Stands in for a #[Lazy] value. Nothing runs until the view uses it; after
 * that it forwards echoes, loops, counts, property reads and method calls to
 * the value.
 *
 * An object is always truthy, so test it with a method of the value
 * (`@if ($related->isNotEmpty())`) or unwrap it (`$related->value()`).
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
final class Deferred implements ArrayAccess, Countable, Htmlable, IteratorAggregate, JsonSerializable, Stringable
{
    private bool $resolved = false;

    private mixed $value = null;

    public function __construct(private readonly Closure $resolver) {}

    public function value(): mixed
    {
        if (! $this->resolved) {
            $this->value = ($this->resolver)();
            $this->resolved = true;
        }

        return $this->value;
    }

    public function resolved(): bool
    {
        return $this->resolved;
    }

    public function __invoke(): mixed
    {
        return $this->value();
    }

    public function __get(string $name): mixed
    {
        $value = $this->value();

        return is_array($value) ? ($value[$name] ?? null) : $value->{$name};
    }

    public function __isset(string $name): bool
    {
        $value = $this->value();

        return is_array($value) ? isset($value[$name]) : isset($value->{$name});
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->value()->{$method}(...$arguments);
    }

    public function getIterator(): Traversable
    {
        $value = $this->value();

        return match (true) {
            $value instanceof Traversable => $value,
            is_array($value) => new ArrayIterator($value),
            default => new ArrayIterator($value === null ? [] : [$value]),
        };
    }

    public function count(): int
    {
        $value = $this->value();

        return is_countable($value) ? count($value) : (int) ($value !== null);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->value()[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->value()[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->value()[$offset]);
    }

    public function toHtml(): string
    {
        $value = $this->value();

        return $value instanceof Htmlable ? $value->toHtml() : e((string) $value);
    }

    public function __toString(): string
    {
        $value = $this->value();

        return $value instanceof Htmlable ? $value->toHtml() : (string) $value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
