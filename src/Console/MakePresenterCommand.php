<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Support\Convention;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Takes a class name or a view name, so all of these make
 * App\Presenters\Archive\MonthPresenter for archive/month.blade.php:
 *
 *     php artisan make:presenter archive.month
 *     php artisan make:presenter Archive/Month
 *     php artisan make:presenter Archive/MonthPresenter
 */
#[AsCommand(name: 'make:presenter', description: 'Create a Presenter class (and its view, when missing)')]
final class MakePresenterCommand extends GeneratorCommand
{
    protected $name = 'make:presenter';

    protected $description = 'Create a Presenter class (and its view, when missing)';

    protected $type = 'Presenter';

    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        if (! $this->option('without-view')) {
            $this->makeView();
        }

        return null;
    }

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/presenter.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/presenter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Presenters';
    }

    /** "archive.month", "archive/month" and "Archive/Month" all become Archive/MonthPresenter. */
    protected function getNameInput(): string
    {
        $segments = preg_split('#[./\\\\]+#', trim((string) $this->argument('name'), " ./\\")) ?: [];
        $segments = array_map(fn (string $segment) => Str::studly($segment), $segments);
        $last = (string) array_pop($segments);
        $segments[] = Str::endsWith($last, 'Presenter') ? $last : $last.'Presenter';

        return implode('/', $segments);
    }

    protected function buildClass($name): string
    {
        $view = $this->option('view');

        // The folder already names the view; the attribute is only needed for another one.
        $attribute = $view && ! in_array($view, $this->conventionalViews($name), true);

        return str_replace(
            ['{{ imports }}', '{{ attribute }}'],
            $attribute ? ["use Atannex\\Intermidiate\\Attributes\\ForView;\n", "#[ForView('{$view}')]\n"] : ['', ''],
            parent::buildClass($name),
        );
    }

    /** Writes a starter Blade file for the Presenter's view, unless one exists. */
    private function makeView(): void
    {
        $class = $this->qualifyClass($this->getNameInput());
        $view = $this->option('view') ?: $this->conventionalViews($class)[0];

        if (str_contains($view, '*') || $this->laravel['view']->exists($view)) {
            return;
        }

        $paths = (array) $this->laravel['config']->get('view.paths', [$this->laravel->resourcePath('views')]);
        $path = rtrim((string) reset($paths), '/\\').'/'.str_replace('.', '/', $view).'.blade.php';

        $this->makeDirectory($path);
        $this->files->put($path, str_replace(
            ['{{ class }}', '{{ view }}'],
            [$class, $view],
            (string) file_get_contents($this->viewStub()),
        ));

        $this->components->info(sprintf('View [%s] created successfully.', $path));
    }

    private function viewStub(): string
    {
        $published = $this->laravel->basePath('stubs/presenter.view.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/presenter.view.stub';
    }

    /** @return list<string> */
    private function conventionalViews(string $class): array
    {
        // Generated classes land in App\Presenters, even when discovery looks elsewhere.
        $namespaces = [...$this->laravel->make(Intermidiate::class)->namespaces(), $this->rootNamespace().'Presenters' => ''];

        return Convention::views($class, $namespaces);
    }

    /** @return list<array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['view', null, InputOption::VALUE_REQUIRED, 'Bind it to another view than the one its name implies (adds #[ForView])'],
            ['without-view', null, InputOption::VALUE_NONE, 'Don\'t create the Blade file'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it exists'],
        ];
    }
}
