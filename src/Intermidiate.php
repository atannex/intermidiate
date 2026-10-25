<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use Atannex\Intermidiate\Exceptions\ViewModelException;
use Atannex\Intermidiate\View\Discovery;
use Closure;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Binds ViewModels to views. When a bound view renders, its ViewModel is
 * built from the view's own data and the members are added to it, so the
 * controller can keep passing raw data.
 */
final class Intermidiate
{
    /** @var array<class-string<ViewModel>, list<string>> */
    private array $bindings = [];

    /** @var array<class-string<ViewModel>, ViewModel> the last one of each class built for a response */
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
     * @param  class-string<ViewModel>  $viewModel
     */
    public function bind(string|array $views, string $viewModel): self
    {
        if (! is_subclass_of($viewModel, ViewModel::class)) {
            throw new ViewModelException("{$viewModel} can't be bound to a view: it doesn't extend ".ViewModel::class.'.');
        }

        $new = array_values(array_diff((array) $views, $this->bindings[$viewModel] ?? []));

        if ($new === []) {
            return $this;
        }

        $this->bindings[$viewModel] = [...($this->bindings[$viewModel] ?? []), ...$new];
        ($this->views)()->composer($new, fn (View $view) => $this->compose($viewModel, $view));

        return $this;
    }

    /**
     * Builds the ViewModel from the view's data and adds its members. Skipped
     * when the view already carries one (a controller returned it).
     *
     * @param  class-string<ViewModel>  $viewModel
     */
    public function compose(string $viewModel, View $view): ?ViewModel
    {
        $data = $view->getData();

        foreach ($data as $value) {
            if ($value instanceof $viewModel) {
                return null;
            }
        }

        $instance = $viewModel::from($data);
        $view->with($instance->toArray());

        return $this->track($instance);
    }

    /** @return array<class-string<ViewModel>, list<string>> */
    public function bindings(): array
    {
        // Config and discovered bindings register when the view factory is first resolved.
        ($this->views)();

        return $this->bindings;
    }

    /**
     * ViewModels with #[ForView] in the configured folders, from the cache
     * file when `view-model:cache` has written one.
     *
     * @return array<class-string<ViewModel>, list<string>>
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
        foreach ($this->discovered() as $viewModel => $views) {
            $this->bind($views, $viewModel);
        }

        return $this;
    }

    /**
     * Writes the discovery cache.
     *
     * @return array<class-string<ViewModel>, list<string>>
     */
    public function cache(): array
    {
        $found = Discovery::within($this->discover);

        if ($this->cache === null) {
            throw new ViewModelException('Set config intermidiate.cache to a file path to cache discovery.');
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
     * @template T of ViewModel
     *
     * @param  T  $viewModel
     * @return T
     *
     * @internal
     */
    public function track(ViewModel $viewModel): ViewModel
    {
        $this->tracked[$viewModel::class] = $viewModel;

        return $viewModel;
    }

    /**
     * The last ViewModel of this class built for a response (for tests).
     *
     * @template T of ViewModel
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function last(string $class): ?ViewModel
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
