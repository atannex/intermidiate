<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Generator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Traversable;
use UnitEnum;

/**
 * @internal A stable description of an object's constructor inputs, for
 * cache keys: models by class, key and updated_at, dates as ISO strings,
 * enums by value, Arrayable and JsonSerializable objects by their data, and
 * any other object by its class and every property (private ones too).
 *
 * Inputs without a reliable identity (closures, generators, lazy
 * collections, resources, unsaved models, graphs nested too deep) give null: the caller then doesn't cache, rather
 * than risk two different inputs sharing a key.
 */
final class Fingerprint
{
    private const MAX_DEPTH = 5;

    public static function of(object $object): ?string
    {
        $constructor = (new ReflectionClass($object))->getConstructor();
        $inputs = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if ($parameter->isPromoted()) {
                $property = new ReflectionProperty($object, $parameter->getName());
                $inputs[$parameter->getName()] = $property->isInitialized($object) ? $property->getValue($object) : null;
            }
        }

        return self::hash($inputs);
    }

    /** A hash of any value, or null when part of it has no reliable identity. */
    public static function hash(mixed $value): ?string
    {
        try {
            return md5(serialize(self::value($value, 0)));
        } catch (Unidentifiable) {
            return null;
        }
    }

    private static function value(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new Unidentifiable;
        }

        $next = fn (mixed $inner) => self::value($inner, $depth + 1);

        return match (true) {
            $value === null, is_scalar($value) => $value,
            is_array($value) => array_map($next, $value),
            ! is_object($value) => throw new Unidentifiable,   // resources
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof UrlRoutable => [$value::class, $value->getRouteKey() ?? throw new Unidentifiable, self::updated($value)],
            $value instanceof Paginator, $value instanceof CursorPaginator => [$value::class, $value instanceof Paginator ? $value->currentPage() : $value->cursor()?->encode(), $value->perPage(), $next($value->items())],
            // Iterating these would consume them or run their query.
            $value instanceof Closure, $value instanceof Generator, $value instanceof LazyCollection => throw new Unidentifiable,
            // Collections item by item, so their models are identified by key and updated_at.
            $value instanceof Traversable => [$value::class, $next(iterator_to_array($value))],
            $value instanceof Arrayable => [$value::class, $next($value->toArray())],
            $value instanceof JsonSerializable => [$value::class, $next($value->jsonSerialize())],
            default => [$value::class, $next((array) $value)],
        };
    }

    private static function updated(object $model): mixed
    {
        // An Eloquent model's stored value, without building a date for it.
        if ($model instanceof Model) {
            $column = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;

            return $column === null ? null : ($model->getAttributes()[$column] ?? null);
        }

        $updated = $model->updated_at ?? null;

        return $updated instanceof DateTimeInterface ? $updated->format(DATE_ATOM) : null;
    }
}
