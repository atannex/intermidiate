<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Exceptions\InvalidViewData;
use Atannex\Intermidiate\Exceptions\MissingViewData;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Illuminate\Container\Container;
use Atannex\Intermidiate\Attributes\Query;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds an object from a bag of data by matching constructor parameters.
 *
 * Each parameter is filled from the data (by name, by position, or loosely by
 * snake_case/camelCase spelling), then from the current route's parameters,
 * then from the query string when it's marked #[Query], then from the
 * container when it has the class type bound, then from its default, then
 * from the container for any other class type, then with null when nullable.
 * Values are checked against the declared type, and converted from strings
 * when coercion is on: a model type is looked up by its route key, so a
 * `Post $post` parameter takes `{post}` (or `{post:slug}`) and answers 404
 * when there's no such post. A route or query value that doesn't fit the
 * type is skipped rather than failing.
 */
final class Hydrator
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<int|string, mixed>  $data
     * @param  bool  $loose  match other spellings and ignore keys the constructor doesn't take (view data)
     * @param  bool  $page  the route serves this class (Route::presenter): a path value that doesn't fit is a 404, even with a default
     * @return T
     */
    public static function build(string $class, array $data, bool $loose = false, bool $page = false): object
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
        $records = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $name = $parameter->getName();
            [$found, $value] = self::find($data, $parameter, $loose);

            if ($found) {
                $arguments[$name] = self::cast($class, $parameter, $value, $coerce, $loose);

                continue;
            }

            // #[Config], #[Old], #[Session], #[CurrentUser].
            [$found, $value] = Ambient::marked($parameter);

            if ($found) {
                $arguments[$name] = $value === null ? null : self::cast($class, $parameter, $value, $coerce, true);

                continue;
            }

            // #[Paginate] / #[Fetch]: queried once every other input is known.
            if (($loader = Loader::for($parameter)) !== null) {
                $records[] = [$parameter, $loader];

                continue;
            }

            // From the request: wrong-looking values fall through to the defaults below.
            $mismatch = null;

            foreach ([fn () => self::fromRoute($parameter), fn () => self::fromQuery($parameter)] as $index => $source) {
                [$found, $value, $field] = $source();

                if ($found) {
                    try {
                        $arguments[$name] = self::cast($class, $parameter, $value, $coerce, true, $field);

                        continue 2;
                    } catch (InvalidViewData $exception) {
                        if ($page && $index === 0) {
                            throw new NotFoundHttpException($exception->getMessage(), $exception);
                        }

                        $mismatch = $exception;

                        break;
                    }
                }
            }

            // $user, $locale, $timezone: known by name.
            [$found, $value] = Ambient::known($parameter);

            if ($found && ($value !== null || $parameter->allowsNull())) {
                $arguments[$name] = $value;

                continue;
            }

            if ($parameter->isDefaultValueAvailable() && ! self::bound($parameter)) {
                continue;
            } elseif (($service = self::service($parameter)) !== null) {
                $arguments[$name] = $service;
            } elseif ($parameter->allowsNull()) {
                $arguments[$name] = null;
            } elseif ($mismatch !== null) {
                // /desks/weather for a Desk enum: no such page, rather than a broken one.
                throw new NotFoundHttpException($mismatch->getMessage(), $mismatch);
            } else {
                throw MissingViewData::for($class, $name, (string) ($parameter->getType() ?? 'mixed'), $available);
            }
        }

        foreach ($records as [$parameter, $loader]) {
            $arguments[$parameter->getName()] = Loader::load($class, $parameter, $loader, $arguments);
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

    /**
     * The current route's parameter of this name (snake_case or camelCase).
     *
     * @return array{bool, mixed, string|null} found, value, the {post:slug} binding field
     */
    private static function fromRoute(ReflectionParameter $parameter): array
    {
        $route = self::request()?->route();

        if (! $route instanceof Route) {
            return [false, null, null];
        }

        try {
            $parameters = $route->parameters();
        } catch (LogicException) {
            return [false, null, null];
        }

        foreach (self::spellings($parameter->getName()) as $key) {
            if (array_key_exists($key, $parameters) && $parameters[$key] !== null) {
                return [true, $parameters[$key], $route->bindingFieldFor($key)];
            }
        }

        return [false, null, null];
    }

    /** @return array{bool, mixed, null} */
    private static function fromQuery(ReflectionParameter $parameter): array
    {
        $query = $parameter->getAttributes(Query::class)[0] ?? null;
        $request = self::request();

        if ($query === null || $request === null) {
            return [false, null, null];
        }

        $key = $query->newInstance()->key;

        foreach ($key !== null ? [$key] : self::spellings($parameter->getName()) as $spelling) {
            $value = $request->query($spelling);

            if ($value !== null && $value !== '') {
                return [true, $value, null];
            }
        }

        return [false, null, null];
    }

    private static function request(): ?Request
    {
        $container = Container::getInstance();

        if (! $container->bound('request')) {
            return null;
        }

        $request = $container->make('request');

        return $request instanceof Request ? $request : null;
    }

    /** @return list<string> */
    private static function spellings(string $name): array
    {
        return array_values(array_unique([$name, Str::snake($name), Str::camel($name)]));
    }

    /**
     * A model (anything UrlRoutable) looked up by its route key, as route model binding does.
     *
     * @return array{bool, mixed}
     */
    private static function bind(?ReflectionType $type, mixed $value, ?string $field): array
    {
        if (! is_string($value) && ! is_int($value)) {
            return [false, $value];
        }

        $candidates = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof ReflectionNamedType || $candidate->isBuiltin()) {
                continue;
            }

            $model = $candidate->getName();

            if (! is_a($model, UrlRoutable::class, true) || is_a($model, ModelPresenter::class, true) || ! (new ReflectionClass($model))->isInstantiable()) {
                continue;
            }

            $instance = (new $model)->resolveRouteBinding($value, $field);

            if ($instance !== null) {
                return [true, $instance];
            }

            if (class_exists(ModelNotFoundException::class)) {
                throw (new ModelNotFoundException)->setModel($model, [$value]);
            }

            throw new NotFoundHttpException("No {$model} for \"{$value}\".");
        }

        return [false, $value];
    }

    private static function cast(string $class, ReflectionParameter $parameter, mixed $value, bool $coerce, bool $loose = false, ?string $field = null): mixed
    {
        $type = $parameter->getType();

        if (Types::accepts($type, $value)) {
            return $value;
        }

        // A presented model reaching a parameter typed with the model (a partial's Presenter).
        if ($value instanceof ModelPresenter && Types::accepts($type, $value->unwrap())) {
            return $value->unwrap();
        }

        if ($coerce) {
            [$ok, $converted] = self::bind($type, $value, $field);

            if ($ok) {
                return $converted;
            }

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
