<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Support\Convention;
use Illuminate\Container\Container;

/**
 * @internal Backs the @presenter directive: builds a Presenter from the
 * variables in scope (for partials and anonymous components that have no
 * controller) and returns its variables to extract.
 */
final class Inline
{
    /**
     * @param  array<string, mixed>  $scope
     * @param  class-string<Presenter>  $class
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    public static function variables(array $scope, string $class, array $with = []): array
    {
        $data = [];

        foreach ($scope as $key => $value) {
            if (! str_starts_with($key, '__')) {
                $data[$key] = $value;
            }
        }

        // Already built for this view (by its binding): its variables are in scope.
        foreach ($data as $value) {
            if ($value instanceof $class) {
                return [];
            }
        }

        return $class::from([...$data, ...$with])->toArray();
    }

    /**
     * Backs a bare @presenter: the Presenter of the view being rendered.
     *
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    public static function forView(string $view, array $scope, array $with = []): array
    {
        $intermidiate = Container::getInstance()->make(Intermidiate::class);
        $class = $intermidiate->presenterForView($view) ?? throw new PresenterException(sprintf(
            '@presenter in [%s] found no Presenter. Create %s, or name one: @presenter(App\\Presenters\\SomePresenter::class).',
            $view, Convention::classes($view, $intermidiate->namespaces())[0] ?? 'one',
        ));

        return self::variables($scope, $class, $with);
    }

    /**
     * The view name of a compiled file, from the view paths.
     *
     * @param  list<string>  $paths
     */
    public static function viewName(string $file, array $paths): ?string
    {
        $file = str_replace('\\', '/', $file);

        foreach ($paths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/').'/';

            if (str_starts_with($file, $path) && str_ends_with($file, '.blade.php')) {
                return str_replace('/', '.', substr($file, strlen($path), -strlen('.blade.php')));
            }
        }

        return null;
    }
}
