<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bindings
    |--------------------------------------------------------------------------
    |
    | Views that get a Presenter built from their own data before they render,
    | with no change to the controller. Keys are view names (wildcards work),
    | values a Presenter class or a list of them.
    |
    |     'date' => App\Presenters\ArchivePresenter::class,
    |     'desks.*' => App\Presenters\DeskPresenter::class,
    |
    */

    'bindings' => [],

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | Namespaces (and the folders they live in) scanned for Presenters that
    | carry #[ForView(...)]. Run `php artisan presenter:cache` in production
    | (or `php artisan optimize`) so the scan doesn't happen on every request.
    |
    */

    'discover' => [
        'App\\Presenters' => app_path('Presenters'),
    ],

    'cache' => base_path('bootstrap/cache/intermidiate.php'),

    /*
    |--------------------------------------------------------------------------
    | The Presenter inside the view
    |--------------------------------------------------------------------------
    |
    | Every exported member becomes a view variable. The Presenter itself is
    | also shared under this name ($presenter->lead, $presenter->only(...)). Null turns it off.
    |
    */

    'variable' => 'presenter',

    /*
    |--------------------------------------------------------------------------
    | Coercion
    |--------------------------------------------------------------------------
    |
    | Route parameters and query strings arrive as strings. With coercion on,
    | "2026" fills an int parameter, "03" an int month, "news" a backed enum
    | and "2026-03-01" a Carbon date. Anything that can't be converted fails
    | with a message naming the parameter.
    |
    */

    'coerce' => true,

    /*
    |--------------------------------------------------------------------------
    | JSON
    |--------------------------------------------------------------------------
    |
    | A Presenter returned from a controller answers requests that expect JSON
    | with its exported data instead of the rendered view.
    |
    */

    'json' => true,

    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    |
    | Default width for zero-padded folios, calendar parameters and page
    | numbers: 2 turns 3 into "03".
    |
    */

    'pad' => 2,

    /*
    |--------------------------------------------------------------------------
    | Calendar
    |--------------------------------------------------------------------------
    |
    | First day of the week for month grids: 0 (Sunday) to 6 (Saturday).
    |
    */

    'week_starts_on' => 1,

];
