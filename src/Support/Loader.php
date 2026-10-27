<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Fetch;
use Atannex\Intermidiate\Attributes\Paginate;
use Atannex\Intermidiate\Attributes\Records;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Intermidiate;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * @internal Runs the query behind a #[Paginate] or #[Fetch] parameter.
 */
final class Loader
{
    /**
     * @param  class-string  $class  the Presenter being built
     * @param  array<string, mixed>  $arguments  the other inputs, already resolved
     */
    public static function load(string $class, ReflectionParameter $parameter, Records $records, array $arguments): mixed
    {
        if (! class_exists(Model::class)) {
            throw new PresenterException("{$class}::\${$parameter->getName()} uses #[".class_basename($records).'], which needs illuminate/database.');
        }

        $type = self::type($parameter);
        $model = self::model($class, $parameter, $records, $type);
        $query = self::query($class, $parameter, $records, $model, $arguments);

        if ($records instanceof Paginate) {
            $perPage = $records->perPage ?? (new $model)->getPerPage();

            $page = match (true) {
                is_a($type, CursorPaginator::class, true) => $query->cursorPaginate($perPage),
                is_a($type, LengthAwarePaginator::class, true) => $query->paginate($perPage, ['*'], $records->pageName),
                is_a($type, Paginator::class, true) => $query->simplePaginate($perPage, ['*'], $records->pageName),
                default => throw new PresenterException("{$class}::\${$parameter->getName()} has #[Paginate], so type it LengthAwarePaginator, Paginator or CursorPaginator."),
            };

            return $page->withQueryString();
        }

        $limit = $records instanceof Fetch ? $records->limit : null;

        if ($type !== null && is_a($type, Model::class, true)) {
            return $query->first();
        }

        $models = $query->when($limit !== null, fn (Builder $query) => $query->limit((int) $limit))->get();

        return $type === 'array' ? $models->all() : $models;
    }

    /** @return class-string<Model> */
    private static function model(string $class, ReflectionParameter $parameter, Records $records, ?string $type): string
    {
        $candidates = match (true) {
            $records->model !== null => [$records->model],
            $type !== null && is_a($type, Model::class, true) => [$type],
            default => self::guesses($parameter->getName()),
        };

        foreach ($candidates as $candidate) {
            if (is_a($candidate, Model::class, true)) {
                return $candidate;
            }
        }

        throw new PresenterException(sprintf(
            '%s::$%s: no model found (tried %s). Name it: #[%s(Post::class)].',
            $class, $parameter->getName(), implode(', ', $candidates), class_basename($records),
        ));
    }

    /** @return list<string> $posts → App\Models\Post, App\Post */
    private static function guesses(string $name): array
    {
        $container = Container::getInstance();
        $namespace = $container->bound(Intermidiate::class) ? $container->make(Intermidiate::class)->appNamespace() : 'App\\';
        $model = Str::studly(Str::singular($name));

        return [$namespace.'Models\\'.$model, $namespace.$model];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $arguments
     * @return Builder<Model>
     */
    private static function query(string $class, ReflectionParameter $parameter, Records $records, string $model, array $arguments): Builder
    {
        $query = $model::query()
            ->with($records->with)
            ->withCount($records->withCount)
            ->when($records->latest !== null, fn (Builder $query) => $query->latest($records->latest))
            ->when($records->oldest !== null, fn (Builder $query) => $query->oldest($records->oldest));

        foreach ($records->scopes as $scope) {
            $query->{$scope}();
        }

        $method = $records->query ?? $parameter->getName().'Query';

        if (! method_exists($class, $method)) {
            if ($records->query !== null) {
                throw new PresenterException("{$class} has no static method {$method}() for #[".class_basename($records).'(query: …)].');
            }

            return $query;
        }

        $reflection = new ReflectionMethod($class, $method);

        if (! $reflection->isStatic()) {
            throw new PresenterException("{$class}::{$method}() narrows the query for \${$parameter->getName()} before the Presenter exists, so make it static.");
        }

        // The builder goes to the first parameter; the rest are the Presenter's other inputs, by name.
        $wanted = array_map(fn (ReflectionParameter $wanted) => $wanted->getName(), $reflection->getParameters());
        $first = $wanted[0] ?? 'query';
        $inputs = [];

        foreach ($parameter->getDeclaringFunction()->getParameters() as $input) {
            if (! in_array($input->getName(), $wanted, true)) {
                continue;
            }

            if (array_key_exists($input->getName(), $arguments)) {
                $inputs[$input->getName()] = $arguments[$input->getName()];
            } elseif ($input->isDefaultValueAvailable()) {
                $inputs[$input->getName()] = $input->getDefaultValue();
            }
        }

        $result = Container::getInstance()->call([$class, $method], [...$inputs, $first => $query]);

        return $result instanceof Builder ? $result : $query;
    }

    private static function type(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }
}
