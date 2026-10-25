<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:presenter', description: 'Create a Presenter class')]
final class MakePresenterCommand extends GeneratorCommand
{
    protected $name = 'make:presenter';

    protected $description = 'Create a Presenter class';

    protected $type = 'Presenter';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/presenter.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/presenter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Presenters';
    }

    protected function buildClass($name): string
    {
        $view = $this->option('view');

        return str_replace(
            ['{{ imports }}', '{{ attribute }}'],
            $view ? ["use Atannex\\Intermidiate\\Attributes\\ForView;\n", "#[ForView('{$view}')]\n"] : ['', ''],
            parent::buildClass($name),
        );
    }

    /** @return list<array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['view', null, InputOption::VALUE_REQUIRED, 'Bind it to this view with #[ForView]'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it exists'],
        ];
    }
}
