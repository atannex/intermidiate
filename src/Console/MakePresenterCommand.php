<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:presenter', description: 'Create a presenter class')]
final class MakePresenterCommand extends GeneratorCommand
{
    protected $name = 'make:presenter';

    protected $description = 'Create a presenter class';

    protected $type = 'Presenter';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/presenter.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/presenter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\ViewModels\\Presenters';
    }

    /** @return list<array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it exists'],
        ];
    }
}
