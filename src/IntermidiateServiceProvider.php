<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use Atannex\Intermidiate\Console\CacheCommand;
use Atannex\Intermidiate\Console\ClearCommand;
use Atannex\Intermidiate\Console\ListCommand;
use Atannex\Intermidiate\Console\MakeModelPresenterCommand;
use Atannex\Intermidiate\Console\MakePresenterCommand;
use Atannex\Intermidiate\Testing\Assertions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

final class IntermidiateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/intermidiate.php', 'intermidiate');

        $this->app->singleton(Intermidiate::class, fn (Application $app) => new Intermidiate(
            fn () => $app['view'],
            (array) $app['config']->get('intermidiate.discover', []),
            $app['config']->get('intermidiate.cache'),
        ));

        $this->app->alias(Intermidiate::class, 'intermidiate');
    }

    public function boot(): void
    {
        // Bindings are registered the first time the view factory is used,
        // so requests that render nothing never scan for Presenters.
        $this->callAfterResolving('view', function () {
            $intermidiate = $this->app->make(Intermidiate::class);

            foreach ((array) $this->app['config']->get('intermidiate.bindings', []) as $view => $presenters) {
                foreach (Arr::wrap($presenters) as $presenter) {
                    $intermidiate->bind((string) $view, $presenter);
                }
            }

            $intermidiate->bindDiscovered();
        });

        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) {
            $blade->directive('presenter', fn (string $expression) => '<?php extract(\\Atannex\\Intermidiate\\View\\Inline::variables(get_defined_vars(), '.$expression.'), EXTR_OVERWRITE); ?>');
        });

        Assertions::register();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/intermidiate.php' => config_path('intermidiate.php')], 'intermidiate-config');
        $this->publishes([
            __DIR__.'/../stubs/presenter.stub' => base_path('stubs/presenter.stub'),
            __DIR__.'/../stubs/model-presenter.stub' => base_path('stubs/model-presenter.stub'),
        ], 'intermidiate-stubs');

        $this->commands([
            MakePresenterCommand::class,
            MakeModelPresenterCommand::class,
            ListCommand::class,
            CacheCommand::class,
            ClearCommand::class,
        ]);

        if (method_exists($this, 'optimizes')) {
            $this->optimizes(optimize: 'presenter:cache', clear: 'presenter:clear');
        }
    }
}
