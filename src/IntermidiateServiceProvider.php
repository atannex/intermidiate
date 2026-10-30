<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use Atannex\Intermidiate\Console\CacheCommand;
use Atannex\Intermidiate\Console\ClearCommand;
use Atannex\Intermidiate\Console\ListCommand;
use Atannex\Intermidiate\Console\MakeModelPresenterCommand;
use Atannex\Intermidiate\Console\MakePresenterCommand;
use Atannex\Intermidiate\Console\TidyCommand;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Http\PresenterController;
use Atannex\Intermidiate\Http\Routed;
use Atannex\Intermidiate\Support\Hydrator;
use Atannex\Intermidiate\Testing\Assertions;
use Atannex\Intermidiate\View\Inline;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use ReflectionClass;
use RuntimeException;

final class IntermidiateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/intermidiate.php', 'intermidiate');

        $this->app->singleton(Intermidiate::class, fn (Application $app) => new Intermidiate(
            fn () => $app['view'],
            self::discover($app),
            $app['config']->get('intermidiate.cache'),
            (bool) $app['config']->get('intermidiate.conventions', true),
            (array) $app['config']->get('intermidiate.models', []),
            self::appNamespace($app),
            $app->runningUnitTests(),
        ));

        $this->app->alias(Intermidiate::class, 'intermidiate');
    }

    /**
     * The configured discovery folders, or the app's own Presenters folder
     * under its PSR-4 namespace (App\Presenters => app/Presenters) by default.
     *
     * @return array<string, string>
     */
    private static function discover(Application $app): array
    {
        $configured = $app['config']->get('intermidiate.discover');

        if ($configured !== null) {
            return (array) $configured;
        }

        return [self::appNamespace($app).'Presenters' => $app->path('Presenters')];
    }

    private static function appNamespace(Application $app): string
    {
        try {
            return $app->getNamespace();
        } catch (RuntimeException) {
            return 'App\\';
        }
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
            $blade->directive('presenter', function (string $expression) use ($blade) {
                $expression = trim($expression);

                // @presenter(Class::class) or @presenter(Class::class, [...]).
                if ($expression !== '' && ! str_starts_with($expression, '[')) {
                    return '<?php extract(\\Atannex\\Intermidiate\\View\\Inline::variables(get_defined_vars(), '.$expression.'), EXTR_OVERWRITE); ?>';
                }

                // Bare @presenter (or @presenter([...])): this view's own Presenter, found by its name.
                $view = Inline::viewName((string) $blade->getPath(), $this->app['view']->getFinder()->getPaths());

                return '<?php extract(\\Atannex\\Intermidiate\\View\\Inline::forView('.var_export((string) $view, true).', get_defined_vars()'.($expression === '' ? '' : ', '.$expression).'), EXTR_OVERWRITE); ?>';
            });

            $blade->directive('meta', fn (string $expression) => '<?php echo \\Atannex\\Intermidiate\\Seo\\Meta::forView(get_defined_vars())->toHtml(); ?>');
        });

        Assertions::register();

        // A Presenter that is a route's action (Route::get('/about', AboutPresenter::class),
        // or #[Get] on the class) isn't autowired: the router gets an empty instance
        // and __invoke() builds the real one from source() and the route.
        $this->app->beforeResolving(Presenter::class, function (string $abstract, array $parameters, Application $app): void {
            if ($parameters === [] && ! $app->bound($abstract) && is_subclass_of($abstract, Presenter::class) && Routed::serves($abstract)) {
                $app->bind($abstract, fn () => Routed::serves($abstract)
                    ? (new ReflectionClass($abstract))->newInstanceWithoutConstructor()
                    : Hydrator::build($abstract, [], loose: true));
            }
        });

        // Route::presenter('/archive/{year}/{month?}', ArchivePresenter::class): a page with no controller.
        if (class_exists(Router::class) && ! Router::hasMacro('presenter')) {
            Router::macro('presenter', function (string $uri, string $presenter, ?string $view = null): Route {
                if (! is_subclass_of($presenter, Presenter::class)) {
                    throw new PresenterException("{$presenter} can't serve a route: it doesn't extend ".Presenter::class.'.');
                }

                /** @var Router $this */
                return $this->get($uri, array_filter([
                    'uses' => PresenterController::class,
                    'presenter' => $presenter,
                    'presenter_view' => $view,
                ]));
            });
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/intermidiate.php' => config_path('intermidiate.php')], 'intermidiate-config');
        $this->publishes([
            __DIR__.'/../stubs/presenter.stub' => base_path('stubs/presenter.stub'),
            __DIR__.'/../stubs/presenter.view.stub' => base_path('stubs/presenter.view.stub'),
            __DIR__.'/../stubs/model-presenter.stub' => base_path('stubs/model-presenter.stub'),
        ], 'intermidiate-stubs');

        $this->commands([
            MakePresenterCommand::class,
            MakeModelPresenterCommand::class,
            ListCommand::class,
            CacheCommand::class,
            ClearCommand::class,
            TidyCommand::class,
        ]);

        // Only on Laravel versions that have optimizes().
        if ((new ReflectionClass(ServiceProvider::class))->hasMethod('optimizes')) {
            $this->optimizes(optimize: 'presenter:cache', clear: 'presenter:clear');
        }
    }
}
