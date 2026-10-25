<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Routing\UrlRoutable;
use ReflectionClass;
use Traversable;
use UnitEnum;

/**
 * @internal A stable, short description of an object's constructor inputs,
 * for cache keys: models by class, key and updated_at, dates as ISO strings,
 * enums by value, services by class.
 */
final class Fingerprint
{
    public static function of(object $object): string
    {
        $constructor = (new ReflectionClass($object))->getConstructor();
        $inputs = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();

            if ($parameter->isPromoted()) {
                $property = new \ReflectionProperty($object, $name);
                $inputs[$name] = $property->isInitialized($object) ? self::value($property->getValue($object)) : null;
            }
        }

        return md5(serialize($inputs));
    }

    private static function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 3) {
            return '…';
        }

        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof UrlRoutable => [$value::class, $value->getRouteKey(), self::updated($value)],
            $value instanceof Paginator, $value instanceof CursorPaginator => [$value::class, $value instanceof Paginator ? $value->currentPage() : $value->cursor()?->encode(), $value->perPage(), self::value($value->items(), $depth + 1)],
            is_array($value) => array_map(fn ($item) => self::value($item, $depth + 1), $value),
            $value instanceof Traversable => array_map(fn ($item) => self::value($item, $depth + 1), iterator_to_array($value)),
            default => $value::class,
        };
    }

    private static function updated(object $model): ?string
    {
        $updated = $model->updated_at ?? null;

        return $updated instanceof DateTimeInterface ? $updated->format(DATE_ATOM) : null;
    }
}
