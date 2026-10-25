<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Support\Convention;
use Atannex\Intermidiate\View\Inline;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Finds wiring the conventions already do:
 *
 *  - `@presenter(\App\Presenters\Components\News\ReelPresenter::class)` in
 *    components/news/reel.blade.php → `@presenter`;
 *  - `#[ForView('archive')]` on ArchivePresenter (with --attributes).
 *
 * Lists them; --fix rewrites the files.
 */
#[AsCommand(name: 'presenter:tidy', description: 'Find (and with --fix remove) Presenter wiring the conventions already do')]
final class TidyCommand extends Command
{
    protected $signature = 'presenter:tidy
        {--fix : Rewrite the files}
        {--attributes : Also remove #[ForView] attributes that name the view the class name already implies}';

    protected $description = 'Find (and with --fix remove) Presenter wiring the conventions already do';

    public function handle(Intermidiate $intermidiate, Filesystem $files): int
    {
        $fix = (bool) $this->option('fix');
        $found = 0;

        foreach ($this->views($files) as [$file, $view]) {
            $contents = $files->get($file);
            $expected = $intermidiate->presenterForView($view);

            $updated = preg_replace_callback(
                '/@presenter\(\s*\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)::class\s*\)/',
                function (array $match) use ($expected, $view, &$found) {
                    if ($expected === null || ltrim($match[1], '\\') !== $expected) {
                        return $match[0];
                    }

                    $found++;
                    $this->components->twoColumnDetail($view, '<fg=gray>'.$match[0].'</> → @presenter');

                    return '@presenter';
                },
                $contents,
            );

            if ($fix && $updated !== null && $updated !== $contents) {
                $files->put($file, $updated);
            }
        }

        if ($this->option('attributes')) {
            $found += $this->attributes($intermidiate, $files, $fix);
        }

        if ($found === 0) {
            $this->components->info('Nothing to tidy.');
        } elseif ($fix) {
            $this->components->info("Tidied {$found} ".($found === 1 ? 'place' : 'places').'. Run `php artisan view:clear`.');
        } else {
            $this->components->info("{$found} ".($found === 1 ? 'place' : 'places').' the conventions already cover. Run with --fix to rewrite them.');
        }

        return self::SUCCESS;
    }

    /** Whether PHP code (not comments or strings) names this class. */
    private static function uses(string $code, string $name): bool
    {
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && class_basename($token[1]) === $name) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{string, string}> [file, view name] */
    private function views(Filesystem $files): array
    {
        $paths = $this->laravel['view']->getFinder()->getPaths();
        $views = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach ($files->allFiles($path) as $file) {
                $view = Inline::viewName($file->getPathname(), $paths);

                if ($view !== null) {
                    $views[] = [$file->getPathname(), $view];
                }
            }
        }

        return $views;
    }

    private function attributes(Intermidiate $intermidiate, Filesystem $files, bool $fix): int
    {
        $found = 0;

        foreach ($intermidiate->discovered() as $class => $views) {
            $file = (string) (new ReflectionClass($class))->getFileName();
            $contents = $files->get($file);

            if (count($views) !== 1 || ! preg_match('/^#\[ForView\(\'([^\']+)\'\)\]\R/m', $contents, $match) || $match[1] !== $views[0]) {
                continue;
            }

            if (! in_array($views[0], Convention::views($class, $intermidiate->namespaces()), true)) {
                continue;
            }

            $found++;
            $this->components->twoColumnDetail(class_basename($class), "<fg=gray>#[ForView('{$views[0]}')]</> → named after its view");

            if ($fix) {
                $updated = str_replace($match[0], '', $contents);

                $import = '/^use Atannex\\\\Intermidiate\\\\Attributes\\\\ForView;\R/m';
                $without = (string) preg_replace($import, '', $updated);

                // Drop the import when nothing but comments still mention it.
                if (! self::uses($without, 'ForView')) {
                    $updated = $without;
                }

                $files->put($file, $updated);
            }
        }

        return $found;
    }
}
