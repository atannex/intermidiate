<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\View\Discovery;
use Closure;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Binds Presenters to views. When a bound view renders, its Presenter is
 * built from the view's own data and the members are added to it, so the
 * controller can keep passing raw data.
 */
final class Intermidiate
{
    /** @var array<class-string<Presenter>, list<string>> */
    private array $bindings = [];

    /** @var array<class-string<Presenter>, Presenter> the last one of each class built for a response */
    private array $tracked = [];

    /**
     * @param  Closure(): Factory  $views  resolved on first bind, so building this never resolves the view factory
     * @param  array<string, string>  $discover  namespace => folder
     */
    public function __construct(
        private readonly Closure $views,
        private readonly array $discover = [],
        private readonly ?string $cache = null,
    ) {}

    /**
     * @param  string|list<string>  $views  view names, wildcards allowed
     * @param  class-string<Presenter>  $presenter
     */
    public function bind(string|array $views, string $presenter): self
    {
        if (! is_subclass_of($presenter, Presenter::class)) {
            throw new PresenterException("{$presenter} can't be bound to a view: it doesn't extend ".Presenter::class.'.');
        }

        $new = array_values(array_diff((array) $views, $this->bindings[$presenter] ?? []));

        if ($new === []) {
            return $this;
        }

        $this->bindings[$presenter] = [...($this->bindings[$presenter] ?? []), ...$new];
        ($this->views)()->composer($new, fn (View $view) => $this->compose($presenter, $view));

        return $this;
    }

    /**
     * Builds the Presenter from the view's data and adds its members. Skipped
     * when the view already carries one (a controller returned it).
     *
     * @param  class-string<Presenter>  $presenter
     */
    public function compose(string $presenter, View $view): ?Presenter
    {
        $data = $view->getData();

        foreach ($data as $value) {
            if ($value instanceof $presenter) {
                return null;
            }
        }

        $instance = $presenter::from($data);
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
     * Presenters with #[ForView] in the configured folders, from the cache
     * file when `presenter:cache` has written one.
     *
     * @return array<class-string<Presenter>, list<string>>
     */
    public function discovered(): array
    {
        if ($this->cache !== null && is_file($this->cache)) {
            return (array) require $this->cache;
        }

        return Discovery::within($this->discover);
    }

    /** Binds everything discovery finds. */
    public function bindDiscovered(): self
    {
        foreach ($this->discovered() as $presenter => $views) {
            $this->bind($views, $presenter);
        }

        return $this;
    }

    /**
     * Writes the discovery cache.
     *
     * @return array<class-string<Presenter>, list<string>>
     */
    public function cache(): array
    {
        $found = Discovery::within($this->discover);

        if ($this->cache === null) {
            throw new PresenterException('Set config intermidiate.cache to a file path to cache discovery.');
        }

        if (! is_dir(dirname($this->cache))) {
            mkdir(dirname($this->cache), 0755, true);
        }

        file_put_contents($this->cache, '<?php return '.var_export($found, true).';'.PHP_EOL);

        return $found;
    }

    public function clearCache(): bool
    {
        return $this->cache !== null && is_file($this->cache) && unlink($this->cache);
    }

    public function cachePath(): ?string
    {
        return $this->cache;
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
