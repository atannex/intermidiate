<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:model-presenter', description: 'Create a model presenter class')]
final class MakeModelPresenterCommand extends GeneratorCommand
{
    protected $name = 'make:model-presenter';

    protected $description = 'Create a model presenter class';

    protected $type = 'Model presenter';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/model-presenter.stub');

        return is_file($published) ? $published : __DIR__.'/../../stubs/model-presenter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Presenters\\Models';
    }

    /** @return list<array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it exists'],
        ];
    }
}
