<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Exceptions\InvalidViewData;
use Atannex\Intermidiate\Exceptions\MissingViewData;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
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
     * Constructor parameters per class, read once: reflecting a constructor
     * and its attributes costs more than building the object.
     *
     * @var array<class-string, list<Parameter>|null> null: no constructor
     */
    private static array $blueprints = [];

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
        $parameters = self::$blueprints[$class] ??= self::blueprint($class);

        if ($parameters === null) {
            return new $class;
        }

        if (! $loose) {
            self::rejectUnknown($class, $parameters, $data);
        }

        $arguments = [];
        $context = null;
        $coerce = null;

        foreach ($parameters as $parameter) {
            if ($parameter->variadic) {
                break;
            }

            $name = $parameter->name;
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

            // From the request: wrong-looking values fall through to the defaults below.
            $mismatch = null;
            $context ??= self::context();

            foreach ([0, 1] as $source) {
                [$found, $value, $field] = $source === 0 ? self::fromRoute($parameter, $context) : self::fromQuery($parameter, $context[0]);

                if (! $found) {
                    continue;
                }

                try {
                    $arguments[$name] = self::cast($class, $parameter, $value, $coerce, true, $field);

                    continue 2;
                } catch (InvalidViewData $exception) {
                    if ($page && $source === 0) {
                        throw new NotFoundHttpException($exception->getMessage(), $exception);
                    }

                    $mismatch = $exception;

                    break;
                }
            }

            // $user, $locale, $timezone: known by name.
            [$found, $value] = Ambient::known($parameter);

            if ($found && ($value !== null || $parameter->nullable)) {
                $arguments[$name] = $value;

                continue;
            }

            if ($parameter->optional && ! self::bound($parameter)) {
                continue;
            } elseif (($service = self::service($parameter)) !== null) {
                $arguments[$name] = $service;
            } elseif ($parameter->nullable) {
                $arguments[$name] = null;
            } elseif ($mismatch !== null) {
                // /desks/weather for a Desk enum: no such page, rather than a broken one.
                throw new NotFoundHttpException($mismatch->getMessage(), $mismatch);
            } else {
                throw MissingViewData::for($class, $name, (string) ($parameter->type ?? 'mixed'), array_values(array_filter(array_keys($data), 'is_string')));
            }
        }

        return new $class(...$arguments);
    }

    /**
     * @param  class-string  $class
     * @return list<Parameter>|null
     */
    private static function blueprint(string $class): ?array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        return $constructor === null ? null : array_map(Parameter::of(...), $constructor->getParameters());
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array{bool, mixed}
     */
    private static function find(array $data, Parameter $parameter, bool $loose): array
    {
        if (array_key_exists($parameter->name, $data)) {
            return [true, $data[$parameter->name]];
        }

        if ($loose) {
            foreach ($parameter->spellings as $key) {
                if (array_key_exists($key, $data)) {
                    return [true, $data[$key]];
                }
            }

            return [false, null];
        }

        if (array_key_exists($parameter->position, $data)) {
            return [true, $data[$parameter->position]];
        }

        return [false, null];
    }

    /**
     * The request, its route and the route's parameters, read once per build.
     *
     * @return array{Request|null, Route|null, array<string, mixed>}
     */
    private static function context(): array
    {
        $request = self::request();
        $route = $request?->route();

        if (! $route instanceof Route) {
            return [$request, null, []];
        }

        try {
            return [$request, $route, $route->parameters()];
        } catch (LogicException) {
            return [$request, null, []];
        }
    }

    /**
     * The current route's parameter of this name (snake_case or camelCase).
     *
     * @param  array{Request|null, Route|null, array<string, mixed>}  $context
     * @return array{bool, mixed, string|null} found, value, the {post:slug} binding field
     */
    private static function fromRoute(Parameter $parameter, array $context): array
    {
        [, $route, $parameters] = $context;

        foreach ($parameter->spellings as $key) {
            if (isset($parameters[$key])) {
                return [true, $parameters[$key], $route?->bindingFieldFor($key)];
            }
        }

        return [false, null, null];
    }

    /** @return array{bool, mixed, null} */
    private static function fromQuery(Parameter $parameter, ?Request $request): array
    {
        if ($parameter->query === null || $request === null) {
            return [false, null, null];
        }

        $key = $parameter->query->key;

        foreach ($key !== null ? [$key] : $parameter->spellings as $spelling) {
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

        return $container->make('request');
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

            if (is_a($model, Model::class, true)) {
                throw (new ModelNotFoundException)->setModel($model, [$value]);
            }

            throw new NotFoundHttpException("No {$model} for \"{$value}\".");
        }

        return [false, $value];
    }

    /**
     * @param  bool|null  $coerce  config intermidiate.coerce, read the first time a value needs converting
     */
    private static function cast(string $class, Parameter $parameter, mixed $value, ?bool &$coerce, bool $loose = false, ?string $field = null): mixed
    {
        $type = $parameter->type;

        if (Types::accepts($type, $value)) {
            return $value;
        }

        // A presented model reaching a parameter typed with the model (a partial's Presenter).
        if ($value instanceof ModelPresenter && Types::accepts($type, $value->unwrap())) {
            return $value->unwrap();
        }

        if ($coerce ??= (bool) Settings::get('coerce', true)) {
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
        if ($loose && $parameter->service && ($service = self::service($parameter)) !== null) {
            return $service;
        }

        throw InvalidViewData::for($class, $parameter->name, (string) $type, $value);
    }

    /** An optional service parameter is still filled when the container has it bound. */
    private static function bound(Parameter $parameter): bool
    {
        return $parameter->class !== null && Container::getInstance()->bound($parameter->class);
    }

    private static function service(Parameter $parameter): ?object
    {
        if ($parameter->class === null) {
            return null;
        }

        try {
            return Container::getInstance()->make($parameter->class);
        } catch (BindingResolutionException) {
            return null;
        }
    }

    /**
     * @param  list<Parameter>  $parameters
     * @param  array<int|string, mixed>  $data
     */
    private static function rejectUnknown(string $class, array $parameters, array $data): void
    {
        if ($parameters !== [] && $parameters[count($parameters) - 1]->variadic) {
            return;
        }

        $names = array_map(fn (Parameter $parameter) => $parameter->name, $parameters);

        foreach (array_keys($data) as $key) {
            if (is_string($key) && ! in_array($key, $names, true)) {
                throw new PresenterException(sprintf(
                    '%s has no constructor parameter $%s.%s It takes: %s.',
                    $class, $key, Suggest::hint($key, $names), $names === [] ? 'nothing' : '$'.implode(', $', $names),
                ));
            }
        }
    }
}
