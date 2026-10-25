<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:view-model', description: 'Create a ViewModel class')]
final class MakeViewModelCommand extends GeneratorCommand
{
    protected $name = 'make:view-model';

    protected $description = 'Create a ViewModel class';

    protected $type = 'ViewModel';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/view-model.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/view-model.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\ViewModels';
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
