<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Http;

use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Support\Hydrator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal Builds a Presenter that serves a route itself: one registered
 * with Route::presenter(), or one whose class is the route's action
 * (an attribute router's #[Get('/about')] on the Presenter).
 *
 * A static source() method, when the Presenter has one, supplies the data:
 *
 *     public static function source(AboutPageManager $page, string $year): ?array
 *
 * Its parameters are filled from the route (models looked up by route key,
 * a 404 when missing) and the container. Returning null is a 404. The
 * returned keys build the Presenter and reach its view as they are, under
 * the Presenter's own members.
 */
final class Routed
{
    /** Whether the current route's action is this Presenter class. */
    public static function serves(string $class): bool
    {
        $route = self::route();

        if ($route === null) {
            return false;
        }

        $uses = $route->getAction('uses');

        return is_string($uses) && in_array(ltrim($uses, '\\'), [$class, $class.'@__invoke'], true);
    }

    /**
     * @param  class-string<Presenter>  $class
     */
    public static function build(string $class): Presenter
    {
        $data = method_exists($class, 'source') ? self::source($class) : [];

        /** @var Presenter $presenter */
        $presenter = Hydrator::build($class, $data, loose: true, page: true);

        return $presenter->with(array_filter($data, fn (mixed $value, int|string $key) => is_string($key) && ! $presenter->has($key), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param  class-string<Presenter>  $class
     * @return array<string, mixed>
     */
    private static function source(string $class): array
    {
        $method = new ReflectionMethod($class, 'source');

        if (! $method->isStatic()) {
            throw new \LogicException("{$class}::source() must be static: it runs before the Presenter exists.");
        }

        $route = self::route();
        $parameters = $route?->parameters() ?? [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            // A model this route doesn't carry gets its default, not an empty model from the container.
            if (! array_key_exists($name, $parameters)) {
                if (is_a($type->getName(), UrlRoutable::class, true) && $parameter->isDefaultValueAvailable()) {
                    $parameters[$name] = $parameter->getDefaultValue();
                }

                continue;
            }

            $value = $parameters[$name];

            if (is_a($type->getName(), UrlRoutable::class, true) && ! $value instanceof UrlRoutable && $value !== null) {
                $model = Container::getInstance()->make($type->getName());
                $parameters[$name] = $model->resolveRouteBinding($value, $route?->bindingFieldFor($name))
                    ?? throw (new ModelNotFoundException)->setModel($type->getName(), [$value]);
            }
        }

        $data = Container::getInstance()->call([$class, 'source'], $parameters);

        if ($data === null) {
            throw new NotFoundHttpException;
        }

        return (array) $data;
    }

    private static function route(): ?Route
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;
        $route = $request instanceof Request ? $request->route() : null;

        return $route instanceof Route ? $route : null;
    }
}
