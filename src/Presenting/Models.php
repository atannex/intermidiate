<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Presenting;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Slicing\Group;
use Closure;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

/**
 * Finds the ModelPresenter for a model and wraps values in it, so a page
 * Presenter's models reach the view already presented:
 *
 *     App\Models\Post          → App\Presenters\Models\PostPresenter
 *     App\Models\Blog\Post     → App\Presenters\Models\Blog\PostPresenter, then …\Models\PostPresenter
 *
 * A config map (intermidiate.models) or #[Presents(Post::class)] on the
 * presenter wins over the name. Parent classes are tried too.
 */
final class Models
{
    /** @var array<class-string, class-string<ModelPresenter>|null> */
    private array $found = [];

    /** @var array<class-string, class-string<ModelPresenter>>|null */
    private ?array $attributed = null;

    /**
     * @param  array<string, string>  $namespaces  discovery namespace => folder
     * @param  Closure(): array<class-string, class-string<ModelPresenter>>  $attributes  #[Presents] from discovery, read on first use
     * @param  array<class-string, class-string<ModelPresenter>>  $map  explicit model => presenter
     */
    public function __construct(
        private readonly array $namespaces,
        private readonly Closure $attributes,
        private array $map = [],
        private readonly string $appNamespace = 'App\\',
    ) {}

    /**
     * @param  class-string  $model
     * @param  class-string<ModelPresenter>  $presenter
     */
    public function map(string $model, string $presenter): void
    {
        if (! is_subclass_of($presenter, ModelPresenter::class)) {
            throw new PresenterException("{$presenter} can't present {$model}: it doesn't extend ".ModelPresenter::class.'.');
        }

        $this->map[$model] = $presenter;
        $this->found = [];
    }

    /**
     * The ModelPresenter for this model (or class), or null when it has none.
     *
     * @return class-string<ModelPresenter>|null
     */
    public function for(object|string $model): ?string
    {
        $class = is_object($model) ? $model::class : $model;

        if (array_key_exists($class, $this->found)) {
            return $this->found[$class];
        }

        foreach ([$class, ...array_values(class_parents($class) ?: [])] as $candidate) {
            if (($presenter = $this->lookup($candidate)) !== null) {
                return $this->found[$class] = $presenter;
            }
        }

        return $this->found[$class] = null;
    }

    /**
     * The class names tried by convention, for messages and generators.
     *
     * @return list<string>
     */
    public function candidates(string $model): array
    {
        $model = ltrim($model, '\\');
        $relative = null;

        foreach ([$this->appNamespace.'Models\\', $this->appNamespace] as $prefix) {
            if (str_starts_with($model, $prefix)) {
                $relative = substr($model, strlen($prefix));
                break;
            }
        }

        $names = array_unique(array_filter([$relative, class_basename($model)]));
        $candidates = [];

        foreach (array_keys($this->namespaces) as $namespace) {
            foreach ($names as $name) {
                $candidates[] = trim((string) $namespace, '\\').'\\Models\\'.$name.'Presenter';
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Wraps the models in a value: a model, a collection, a paginator (which
     * keeps its links) or a Group (which keeps its numbers). Anything else,
     * and models without a presenter, pass through.
     *
     * @param  class-string<ModelPresenter>|null  $presenter  force this one instead of looking it up
     */
    public function wrap(mixed $value, ?string $presenter = null): mixed
    {
        return match (true) {
            $value instanceof ModelPresenter, $value instanceof Closure, $value === null, is_scalar($value), is_array($value) => $value,
            $value instanceof Collection => $this->each($value, $presenter),
            $value instanceof Group => $value->map(fn (mixed $item) => $this->one($item, $presenter)),
            $value instanceof AbstractPaginator, $value instanceof AbstractCursorPaginator => $this->paginator($value, $presenter),
            is_object($value) => $this->one($value, $presenter),
            default => $value,
        };
    }

    /** @param  class-string<ModelPresenter>|null  $presenter */
    private function one(mixed $item, ?string $presenter): mixed
    {
        if (! is_object($item) || $item instanceof ModelPresenter || $item instanceof Collection) {
            return $item;
        }

        $presenter ??= $this->for($item);

        return $presenter === null ? $item : $presenter::make($item);
    }

    /**
     * @param  Collection<array-key, mixed>  $items
     * @param  class-string<ModelPresenter>|null  $presenter
     * @return Collection<array-key, mixed>
     */
    private function each(Collection $items, ?string $presenter): Collection
    {
        $wrapped = $items->toBase()->map(fn (mixed $item) => $this->one($item, $presenter));

        // Nothing to present: hand back the original (an Eloquent collection stays one).
        return $wrapped->contains(fn (mixed $item) => $item instanceof ModelPresenter) ? $wrapped : $items;
    }

    /** @param  class-string<ModelPresenter>|null  $presenter */
    private function paginator(AbstractPaginator|AbstractCursorPaginator $paginator, ?string $presenter): AbstractPaginator|AbstractCursorPaginator
    {
        $items = $paginator->getCollection();
        $wrapped = $this->each($items, $presenter);

        if ($wrapped === $items) {
            return $paginator;
        }

        // A copy, so the Presenter's own $this->posts keeps the models.
        $copy = clone $paginator;
        $copy->setCollection($wrapped);

        return $copy;
    }

    /** @return class-string<ModelPresenter>|null */
    private function lookup(string $class): ?string
    {
        if (isset($this->map[$class])) {
            return $this->map[$class];
        }

        $this->attributed ??= ($this->attributes)();

        if (isset($this->attributed[$class])) {
            return $this->attributed[$class];
        }

        foreach ($this->candidates($class) as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, ModelPresenter::class)) {
                return $candidate;
            }
        }

        return null;
    }
}
