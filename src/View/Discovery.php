<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\Support\Schema;
use Atannex\Intermidiate\ViewModel;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/** Finds ViewModels carrying #[ForView] in PSR-4 folders. */
final class Discovery
{
    /**
     * @param  array<string, string>  $namespaces  namespace => folder
     * @return array<class-string<ViewModel>, list<string>> class => views
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

                if (! class_exists($class) || ! is_subclass_of($class, ViewModel::class) || (new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $views = Schema::of($class)->views;

                if ($views !== []) {
                    $found[$class] = $views;
                }
            }
        }

        ksort($found);

        return $found;
    }
}
