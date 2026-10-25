<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Attributes\Presents;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/** Finds Presenters in PSR-4 folders. */
final class Discovery
{
    /**
     * Presenters carrying #[ForView].
     *
     * @param  array<string, string>  $namespaces  namespace => folder
     * @return array<class-string<Presenter>, list<string>> class => views
     */
    public static function within(array $namespaces): array
    {
        return array_filter(self::presenters($namespaces), fn (array $views) => $views !== []);
    }

    /**
     * Every concrete Presenter, with its #[ForView] views (empty when it has none).
     *
     * @param  array<string, string>  $namespaces  namespace => folder
     * @return array<class-string<Presenter>, list<string>> class => views
     */
    public static function presenters(array $namespaces): array
    {
        $found = [];

        foreach (self::classes($namespaces, Presenter::class) as $reflection) {
            // Only the attribute is read here; members are reflected when the view renders.
            $views = [];

            foreach ($reflection->getAttributes(ForView::class) as $attribute) {
                array_push($views, ...$attribute->newInstance()->views);
            }

            $found[$reflection->getName()] = array_values(array_unique($views));
        }

        ksort($found);

        return $found;
    }

    /**
     * ModelPresenters carrying #[Presents].
     *
     * @param  array<string, string>  $namespaces  namespace => folder
     * @return array<class-string, class-string<ModelPresenter>> model => presenter
     */
    public static function models(array $namespaces): array
    {
        $found = [];

        foreach (self::classes($namespaces, ModelPresenter::class) as $reflection) {
            foreach ($reflection->getAttributes(Presents::class) as $attribute) {
                foreach ($attribute->newInstance()->models as $model) {
                    $found[$model] ??= $reflection->getName();
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @param  array<string, string>  $namespaces
     * @param  class-string  $parent
     * @return list<ReflectionClass<object>>
     */
    private static function classes(array $namespaces, string $parent): array
    {
        $found = [];

        foreach ($namespaces as $namespace => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen(rtrim($directory, '/\\')) + 1, -4);
                $class = trim($namespace, '\\').'\\'.str_replace(['/', '\\'], '\\', $relative);

                if (! class_exists($class) || ! is_subclass_of($class, $parent)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if (! $reflection->isAbstract()) {
                    $found[] = $reflection;
                }
            }
        }

        return $found;
    }
}
