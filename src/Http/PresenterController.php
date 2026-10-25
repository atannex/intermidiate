<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Http;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenter;
use Illuminate\Http\Request;

/**
 * @internal Serves Route::presenter(): builds the Presenter from its source()
 * (when it has one), the route's parameters (models included), #[Query]
 * inputs and the container, and returns it, so it renders its view or
 * answers JSON.
 */
final class PresenterController
{
    public function __invoke(Request $request): Presenter
    {
        $action = $request->route()?->getAction() ?? [];
        $class = $action['presenter'] ?? throw new PresenterException('This route was not registered with Route::presenter().');

        /** @var class-string<Presenter> $class */
        $presenter = Routed::build($class);

        return isset($action['presenter_view']) ? $presenter->view($action['presenter_view']) : $presenter;
    }
}
