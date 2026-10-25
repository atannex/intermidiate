<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Console;

use Atannex\Intermidiate\Intermidiate;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Named after a model, the presenter is found with no attribute:
 *
 *     php artisan make:model-presenter Post                 # Presenters/Models/PostPresenter for App\Models\Post
 *     php artisan make:model-presenter StoryCard --model=Post   # any name, with #[Presents(Post::class)]
 */
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

    /** "Post" names a model, so it becomes PostPresenter; other names are kept. */
    protected function getNameInput(): string
    {
        $name = str_replace('\\', '/', trim((string) $this->argument('name')));

        return ! Str::endsWith($name, 'Presenter') && $this->model($name) !== null ? $name.'Presenter' : $name;
    }

    protected function buildClass($name): string
    {
        $model = $this->option('model') !== null
            ? ($this->model((string) $this->option('model')) ?? $this->qualifyModel((string) $this->option('model')))
            : $this->model(Str::beforeLast(class_basename($name), 'Presenter') ?: class_basename($name));

        // The class name already points at the model; the attribute is only needed otherwise.
        $attribute = $model !== null && ! in_array($name, $this->laravel->make(Intermidiate::class)->models()->candidates($model), true)
            && ! in_array($name, $this->conventional($model), true);

        return str_replace(
            ['{{ imports }}', '{{ attribute }}', '{{ model }}'],
            [
                $attribute ? "use Atannex\\Intermidiate\\Attributes\\Presents;\n" : '',
                $attribute ? '#[Presents(\\'.$model."::class)]\n" : '',
                $model === null ? 'mixed' : '\\'.$model,
            ],
            parent::buildClass($name),
        );
    }

    /** @return class-string|null */
    private function model(string $name): ?string
    {
        $qualified = $this->qualifyModel(str_replace('/', '\\', $name));

        return class_exists($qualified) ? $qualified : (class_exists($name) ? ltrim($name, '\\') : null);
    }

    /**
     * Where generated presenters land (App\Presenters\Models\…), even when discovery looks elsewhere.
     *
     * @return list<string>
     */
    private function conventional(string $model): array
    {
        $base = $this->rootNamespace().'Presenters\\Models\\';
        $relative = Str::after($model, $this->rootNamespace().'Models\\');

        return array_values(array_unique([$base.$relative.'Presenter', $base.class_basename($model).'Presenter']));
    }

    /** @return list<array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model it presents (adds #[Presents] when the name doesn\'t say so)'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it exists'],
        ];
    }
}
