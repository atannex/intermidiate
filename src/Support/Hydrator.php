<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Exceptions\InvalidViewData;
use Atannex\Intermidiate\Exceptions\MissingViewData;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds an object from a bag of data by matching constructor parameters.
 *
 * Each parameter is filled from the data (by name, by position, or loosely by
 * snake_case/camelCase spelling), then from the container when it has the
 * class type bound, then from its default, then from the container for any
 * other class type, then with null when nullable. Values are checked against
 * the declared type, and converted from strings when coercion is on.
 */
final class Hydrator
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<int|string, mixed>  $data
     * @param  bool  $loose  match other spellings and ignore keys the constructor doesn't take (view data)
     * @return T
     */
    public static function build(string $class, array $data, bool $loose = false): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();

        if (! $loose) {
            self::rejectUnknown($class, $parameters, $data);
        }

        $coerce = (bool) Settings::get('coerce', true);
        $available = array_values(array_filter(array_keys($data), 'is_string'));
        $arguments = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $name = $parameter->getName();
            [$found, $value] = self::find($data, $parameter, $loose);

            if ($found) {
                $arguments[$name] = self::cast($class, $parameter, $value, $coerce, $loose);
            } elseif ($parameter->isDefaultValueAvailable() && ! self::bound($parameter)) {
                continue;
            } elseif (($service = self::service($parameter)) !== null) {
                $arguments[$name] = $service;
            } elseif ($parameter->allowsNull()) {
                $arguments[$name] = null;
            } else {
                throw MissingViewData::for($class, $name, (string) ($parameter->getType() ?? 'mixed'), $available);
            }
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array{bool, mixed}
     */
    private static function find(array $data, ReflectionParameter $parameter, bool $loose): array
    {
        $name = $parameter->getName();
        $keys = $loose ? array_unique([$name, Str::snake($name), Str::camel($name)]) : [$name];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return [true, $data[$key]];
            }
        }

        if (! $loose && array_key_exists($parameter->getPosition(), $data)) {
            return [true, $data[$parameter->getPosition()]];
        }

        return [false, null];
    }

    private static function cast(string $class, ReflectionParameter $parameter, mixed $value, bool $coerce, bool $loose = false): mixed
    {
        $type = $parameter->getType();

        if (Types::accepts($type, $value)) {
            return $value;
        }

        if ($coerce) {
            [$ok, $converted] = Types::coerce($type, $value);

            if ($ok) {
                return $converted;
            }
        }

        // In view data, a variable that merely shares a service parameter's name
        // (a page's $navigation reaching a layout's Navigation $navigation).
        if ($loose && self::isService($parameter) && ($service = self::service($parameter)) !== null) {
            return $service;
        }

        throw InvalidViewData::for($class, $parameter->getName(), (string) $type, $value);
    }

    /** A class-typed parameter for a service: not a model, enum, date or collection. */
    private static function isService(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $name = $type->getName();

        return ! enum_exists($name)
            && ! is_a($name, 'Illuminate\Database\Eloquent\Model', true)
            && ! is_a($name, \DateTimeInterface::class, true)
            && ! is_a($name, \Traversable::class, true)
            && ! is_a($name, Arrayable::class, true);
    }

    /** An optional service parameter is still filled when the container has it bound. */
    private static function bound(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && ! $type->isBuiltin() && Container::getInstance()->bound($type->getName());
    }

    private static function service(ReflectionParameter $parameter): ?object
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        try {
            return Container::getInstance()->make($type->getName());
        } catch (BindingResolutionException) {
            return null;
        }
    }

    /**
     * @param  list<ReflectionParameter>  $parameters
     * @param  array<int|string, mixed>  $data
     */
    private static function rejectUnknown(string $class, array $parameters, array $data): void
    {
        $names = array_map(fn (ReflectionParameter $parameter) => $parameter->getName(), $parameters);
        $variadic = $parameters !== [] && end($parameters)->isVariadic();

        foreach (array_keys($data) as $key) {
            if (is_string($key) && ! in_array($key, $names, true) && ! $variadic) {
                throw new PresenterException(sprintf(
                    '%s has no constructor parameter $%s.%s It takes: %s.',
                    $class, $key, Suggest::hint($key, $names), $names === [] ? 'nothing' : '$'.implode(', $', $names),
                ));
            }
        }
    }
}
