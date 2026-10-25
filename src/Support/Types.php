<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use Throwable;

/** @internal Checks values against declared types and converts loose input. */
final class Types
{
    public static function accepts(?ReflectionType $type, mixed $value): bool
    {
        return match (true) {
            $type === null => true,
            $type instanceof ReflectionUnionType => self::any($type->getTypes(), fn ($member) => self::accepts($member, $value)),
            $type instanceof ReflectionIntersectionType => ! self::any($type->getTypes(), fn ($member) => ! self::accepts($member, $value)),
            $value === null => $type->allowsNull(),
            $type instanceof ReflectionNamedType => self::named($type->getName(), $value),
            default => true,
        };
    }

    /**
     * Converts a string, number or array into the declared type, as route
     * parameters and query strings need.
     *
     * @return array{bool, mixed} whether it worked, and the converted value
     */
    public static function coerce(?ReflectionType $type, mixed $value): array
    {
        $candidates = match (true) {
            $type instanceof ReflectionUnionType => $type->getTypes(),
            $type instanceof ReflectionNamedType => [$type],
            default => [],
        };

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof ReflectionNamedType) {
                continue;
            }

            [$ok, $converted] = self::convert($candidate->getName(), $value);

            if ($ok) {
                return [true, $converted];
            }
        }

        return [false, $value];
    }

    private static function named(string $name, mixed $value): bool
    {
        return match ($name) {
            'mixed' => true,
            'null' => $value === null,
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            'object' => is_object($value),
            default => $value instanceof $name,
        };
    }

    /**
     * @return array{bool, mixed}
     */
    private static function convert(string $name, mixed $value): array
    {
        $no = [false, $value];

        try {
            return match (true) {
                $name === 'int' => is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) ? [true, (int) $value]
                    : (is_float($value) && floor($value) === $value ? [true, (int) $value] : $no),
                $name === 'float' => is_string($value) && is_numeric($value) ? [true, (float) $value] : $no,
                $name === 'string' => match (true) {
                    is_int($value), is_float($value), $value instanceof Stringable => [true, (string) $value],
                    $value instanceof BackedEnum => [true, (string) $value->value],
                    default => $no,
                },
                $name === 'bool' => is_scalar($value) && ($bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) !== null
                    ? [true, $bool] : $no,
                $name === 'array' => $value instanceof Arrayable ? [true, $value->toArray()] : $no,
                is_subclass_of($name, BackedEnum::class) => self::enum($name, $value),
                is_a($name, DateTimeInterface::class, true) => self::date($name, $value),
                is_a($name, Enumerable::class, true) && is_iterable($value) && ! interface_exists($name) => [true, new $name($value)],
                default => $no,
            };
        } catch (Throwable) {
            return $no;
        }
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array{bool, mixed}
     */
    private static function enum(string $enum, mixed $value): array
    {
        if (! is_int($value) && ! is_string($value)) {
            return [false, $value];
        }

        $case = $enum::tryFrom($value);

        if ($case === null && is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $case = $enum::tryFrom((int) $value);
        }

        return $case === null ? [false, $value] : [true, $case];
    }

    /**
     * @return array{bool, mixed}
     */
    private static function date(string $class, mixed $value): array
    {
        if (! is_string($value) && ! is_int($value)) {
            return [false, $value];
        }

        $value = is_int($value) ? '@'.$value : $value;

        return [true, match (true) {
            interface_exists($class) => Carbon::parse($value),
            is_a($class, CarbonInterface::class, true) => $class::parse($value),
            default => new $class($value),
        }];
    }

    /**
     * @param  array<int, ReflectionType>  $types
     */
    private static function any(array $types, callable $test): bool
    {
        foreach ($types as $type) {
            if ($test($type)) {
                return true;
            }
        }

        return false;
    }
}
