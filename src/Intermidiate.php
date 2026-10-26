<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use Atannex\Intermidiate\Exceptions\MissingViewData;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Presenting\Models;
use Atannex\Intermidiate\Support\Convention;
use Atannex\Intermidiate\View\Discovery;
use Closure;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use WeakMap;

/**
 * Binds Presenters to views. When a bound view renders, its Presenter is
 * built from the view's own data and the members are added to it, so the
 * controller can keep passing raw data.
 *
 * Presenters in the discovery folders are bound by attribute (#[ForView]) or,
 * without one, by where they live: Presenters/Archive/MonthPresenter.php
 * serves resources/views/archive/month.blade.php.
 */
final class Intermidiate
{
    /** @var array<class-string<Presenter>, list<string>> */
    private array $bindings = [];

    /** @var array<class-string<Presenter>, Presenter> the last one of each class built for a response */
    private array $tracked = [];

    /**
     * What discovery found, kept for this instance: without a cache file a
     * scan reads every Presenter class, and every bare @presenter view asks.
     *
     * @var array{attributes: array<class-string<Presenter>, list<string>>, conventions: array<class-string<Presenter>, list<string>>, models: array<class-string, class-string<ModelPresenter>>}|null
     */
    private ?array $discovery = null;

    /** @var WeakMap<View, array<class-string<Presenter>, true>> */
    private WeakMap $composed;

    private Models $models;

    /**
     * @param  Closure(): Factory  $views  resolved on first bind, so building this never resolves the view factory
     * @param  array<string, string>  $discover  namespace => folder
     * @param  bool  $conventions  bind Presenters without #[ForView] to the view named after their path
     * @param  array<class-string, class-string<ModelPresenter>>  $models  model => ModelPresenter, ahead of #[Presents] and names
     */
    public function __construct(
        private readonly Closure $views,
        private readonly array $discover = [],
        private readonly ?string $cache = null,
        private readonly bool $conventions = true,
        array $models = [],
        private readonly string $appNamespace = 'App\\',
    ) {
        $this->composed = new WeakMap;
        $this->models = new Models($discover, fn () => $this->discovery()['models'], [], $appNamespace);

        foreach ($models as $model => $presenter) {
            $this->models->map($model, $presenter);
        }
    }

    /**
     * Presents this model with this ModelPresenter, wherever it reaches a view.
     *
     * @param  class-string  $model
     * @param  class-string<ModelPresenter>  $presenter
     */
    public function present(string $model, string $presenter): self
    {
        $this->models->map($model, $presenter);

        return $this;
    }

    /** The app's root namespace, "App\\" unless composer.json says otherwise. */
    public function appNamespace(): string
    {
        return $this->appNamespace;
    }

    /** Finds ModelPresenters for models and wraps values in them. */
    public function models(): Models
    {
        return $this->models;
    }

    /**
     * @param  string|list<string>  $views  view names, wildcards allowed
     * @param  class-string<Presenter>  $presenter
     * @param  bool  $implicit  bound by convention: a view rendered without the data the Presenter needs is left alone
     */
    public function bind(string|array $views, string $presenter, bool $implicit = false): self
    {
        if (! is_subclass_of($presenter, Presenter::class)) {
            throw new PresenterException("{$presenter} can't be bound to a view: it doesn't extend ".Presenter::class.'.');
        }

        $new = array_values(array_diff((array) $views, $this->bindings[$presenter] ?? []));

        if ($new === []) {
            return $this;
        }

        $this->bindings[$presenter] = [...($this->bindings[$presenter] ?? []), ...$new];
        ($this->views)()->composer($new, fn (View $view) => $this->compose($presenter, $view, $implicit));

        return $this;
    }

    /**
     * Builds the Presenter from the view's data and adds its members. Skipped
     * when the view already carries one (a controller returned it).
     *
     * @param  class-string<Presenter>  $presenter
     * @param  bool  $implicit  skip, rather than fail, when the view lacks a required input
     */
    public function compose(string $presenter, View $view, bool $implicit = false): ?Presenter
    {
        if (isset($this->composed[$view][$presenter])) {
            return null;
        }

        $data = $view->getData();

        foreach ($data as $value) {
            if ($value instanceof $presenter) {
                return null;
            }
        }

        // Shared data ($errors, View::share()) isn't merged into a view until it renders.
        $factory = ($this->views)();
        $shared = method_exists($factory, 'getShared') ? $factory->getShared() : [];
        unset($shared['__env'], $shared['app']);

        try {
            $instance = $presenter::from([...$shared, ...$data]);
        } catch (MissingViewData $exception) {
            // A partial that shares a convention Presenter's name but is
            // rendered with other data (or builds it itself with @presenter).
            if ($implicit) {
                return null;
            }

            throw $exception;
        }

        $this->composed[$view] = [...($this->composed[$view] ?? []), $presenter => true];
        $view->with($instance->toArray());

        return $this->track($instance);
    }

    /** @return array<class-string<Presenter>, list<string>> */
    public function bindings(): array
    {
        // Config and discovered bindings register when the view factory is first resolved.
        ($this->views)();

        return $this->bindings;
    }

    /**
     * Presenters in the discovery folders and their views, by attribute or
     * by convention, from the cache file when `presenter:cache` has written one.
     *
     * @return array<class-string<Presenter>, list<string>>
     */
    public function discovered(): array
    {
        ['attributes' => $attributes, 'conventions' => $conventions] = $this->discovery();
        $found = [...$attributes, ...$conventions];
        ksort($found);

        return $found;
    }

    /** Binds everything discovery finds. */
    public function bindDiscovered(): self
    {
        ['attributes' => $attributes, 'conventions' => $conventions] = $this->discovery();

        foreach ($attributes as $presenter => $views) {
            $this->bind($views, $presenter);
        }

        foreach ($conventions as $presenter => $views) {
            $this->bind($views, $presenter, implicit: true);
        }

        return $this;
    }

    /**
     * The view a Presenter without #[ForView] renders: the existing view
     * named after its path below a discovery namespace.
     *
     * @param  class-string<Presenter>  $presenter
     */
    public function conventionalView(string $presenter): ?string
    {
        if (! $this->conventions) {
            return null;
        }

        $factory = ($this->views)();

        return Convention::view($presenter, $this->discover, fn (string $view) => $factory->exists($view));
    }

    /**
     * The Presenter for a view: one bound to exactly that name (#[ForView],
     * config, bind()), else the class its name implies (archive.month →
     * Presenters\Archive\MonthPresenter).
     *
     * @return class-string<Presenter>|null
     */
    public function presenterForView(string $view): ?string
    {
        foreach ([...$this->bindings(), ...$this->discovered()] as $presenter => $views) {
            if (in_array($view, $views, true)) {
                return $presenter;
            }
        }

        foreach (Convention::classes($view, $this->discover) as $class) {
            if (class_exists($class) && is_subclass_of($class, Presenter::class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The view names tried for a Presenter, for messages and generators.
     *
     * @param  class-string<Presenter>|string  $presenter
     * @return list<string>
     */
    public function conventionalViews(string $presenter): array
    {
        return Convention::views($presenter, $this->discover);
    }

    /** @return array<string, string> namespace => folder */
    public function namespaces(): array
    {
        return $this->discover;
    }

    /**
     * Writes the discovery cache.
     *
     * @return array<class-string<Presenter>, list<string>>
     */
    public function cache(): array
    {
        if ($this->cache === null) {
            throw new PresenterException('Set config intermidiate.cache to a file path to cache discovery.');
        }

        $found = $this->scan();

        if (! is_dir(dirname($this->cache))) {
            mkdir(dirname($this->cache), 0755, true);
        }

        file_put_contents($this->cache, '<?php return '.var_export($found, true).';'.PHP_EOL);
        $this->discovery = null;

        return $this->discovered();
    }

    public function clearCache(): bool
    {
        $this->discovery = null;

        return $this->cache !== null && is_file($this->cache) && unlink($this->cache);
    }

    public function cachePath(): ?string
    {
        return $this->cache;
    }

    /**
     * @return array{attributes: array<class-string<Presenter>, list<string>>, conventions: array<class-string<Presenter>, list<string>>, models: array<class-string, class-string<ModelPresenter>>}
     */
    private function discovery(): array
    {
        return $this->discovery ??= $this->discover();
    }

    /**
     * @return array{attributes: array<class-string<Presenter>, list<string>>, conventions: array<class-string<Presenter>, list<string>>, models: array<class-string, class-string<ModelPresenter>>}
     */
    private function discover(): array
    {
        if ($this->cache !== null && is_file($this->cache)) {
            $cached = (array) require $this->cache;

            // Caches written before conventions hold only the attribute map.
            return isset($cached['attributes'])
                ? ['attributes' => (array) $cached['attributes'], 'conventions' => (array) ($cached['conventions'] ?? []), 'models' => (array) ($cached['models'] ?? [])]
                : ['attributes' => $cached, 'conventions' => [], 'models' => []];
        }

        return $this->scan();
    }

    /**
     * @return array{attributes: array<class-string<Presenter>, list<string>>, conventions: array<class-string<Presenter>, list<string>>, models: array<class-string, class-string<ModelPresenter>>}
     */
    private function scan(): array
    {
        $attributes = [];
        $conventions = [];

        foreach (Discovery::presenters($this->discover) as $presenter => $views) {
            if ($views !== []) {
                $attributes[$presenter] = $views;
            } elseif (($view = $this->conventionalView($presenter)) !== null) {
                $conventions[$presenter] = [$view];
            }
        }

        return ['attributes' => $attributes, 'conventions' => $conventions, 'models' => Discovery::models($this->discover)];
    }

    /**
     * @template T of Presenter
     *
     * @param  T  $presenter
     * @return T
     *
     * @internal
     */
    public function track(Presenter $presenter): Presenter
    {
        $this->tracked[$presenter::class] = $presenter;

        return $presenter;
    }

    /**
     * The last Presenter of this class built for a response (for tests).
     *
     * @template T of Presenter
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function last(string $class): ?Presenter
    {
        foreach (array_reverse($this->tracked, true) as $tracked) {
            if ($tracked instanceof $class) {
                return $tracked;
            }
        }

        return null;
    }

    public function forgetTracked(): void
    {
        $this->tracked = [];
    }
}
