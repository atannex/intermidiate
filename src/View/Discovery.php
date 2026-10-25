<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Presenter;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/** Finds Presenters carrying #[ForView] in PSR-4 folders. */
final class Discovery
{
    /**
     * @param  array<string, string>  $namespaces  namespace => folder
     * @return array<class-string<Presenter>, list<string>> class => views
     */
    public static function within(array $namespaces): array
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

                if (! class_exists($class) || ! is_subclass_of($class, Presenter::class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if ($reflection->isAbstract()) {
                    continue;
                }

                // Only the attribute is read here; members are reflected when the view renders.
                $views = [];

                foreach ($reflection->getAttributes(ForView::class) as $attribute) {
                    array_push($views, ...$attribute->newInstance()->views);
                }

                $views = array_values(array_unique($views));

                if ($views !== []) {
                    $found[$class] = $views;
                }
            }
        }

        ksort($found);

        return $found;
    }
}
