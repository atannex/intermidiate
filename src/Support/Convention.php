<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Illuminate\Support\Str;

/**
 * Maps a Presenter's place in the folder tree to a view name, so a class
 * needs no #[ForView] and no config entry:
 *
 *     App\Presenters\ArchivePresenter            → archive
 *     App\Presenters\Archive\MonthPresenter      → archive.month
 *     App\Presenters\Layouts\AppPresenter        → layouts.app
 *     App\Presenters\MonthlyArchivePresenter     → monthly-archive (or monthly_archive)
 */
final class Convention
{
    /**
     * The view names this class could stand for, most likely first.
     *
     * @param  array<string, string>  $namespaces  namespace => folder, as in config intermidiate.discover
     * @return list<string>
     */
    public static function views(string $class, array $namespaces): array
    {
        $segments = explode('\\', self::relative($class, $namespaces));
        $last = (string) array_pop($segments);
        $segments[] = Str::endsWith($last, 'Presenter') && $last !== 'Presenter' ? Str::beforeLast($last, 'Presenter') : $last;

        $kebab = implode('.', array_map(fn (string $segment) => Str::kebab($segment), $segments));
        $snake = implode('.', array_map(fn (string $segment) => Str::snake($segment), $segments));

        return array_values(array_unique([$kebab, $snake]));
    }

    /**
     * The other way round: the classes a view name stands for, one per
     * discovery namespace (components.news.reel → …\Components\News\ReelPresenter).
     *
     * @param  array<string, string>  $namespaces
     * @return list<string>
     */
    public static function classes(string $view, array $namespaces): array
    {
        if (str_contains($view, '::')) {
            return [];
        }

        $relative = implode('\\', array_map(fn (string $segment) => Str::studly($segment), explode('.', $view))).'Presenter';

        return array_map(fn (string $namespace) => trim($namespace, '\\').'\\'.$relative, array_keys($namespaces));
    }

    /**
     * The first of views() that exists.
     *
     * @param  array<string, string>  $namespaces
     * @param  callable(string): bool  $exists
     */
    public static function view(string $class, array $namespaces, callable $exists): ?string
    {
        foreach (self::views($class, $namespaces) as $view) {
            if ($exists($view)) {
                return $view;
            }
        }

        return null;
    }

    /**
     * The class name below the longest matching discovery namespace (just the
     * basename when it lives elsewhere).
     *
     * @param  array<string, string>  $namespaces
     */
    public static function relative(string $class, array $namespaces): string
    {
        $class = ltrim($class, '\\');
        $best = null;

        foreach (array_keys($namespaces) as $namespace) {
            $prefix = trim((string) $namespace, '\\').'\\';

            if (str_starts_with($class, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? class_basename($class) : substr($class, strlen($best));
    }
}
