<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bindings
    |--------------------------------------------------------------------------
    |
    | Views that get a ViewModel built from their own data before they render,
    | with no change to the controller. Keys are view names (wildcards work),
    | values a ViewModel class or a list of them.
    |
    |     'date' => App\ViewModels\ArchiveViewModel::class,
    |     'desks.*' => App\ViewModels\DeskViewModel::class,
    |
    */

    'bindings' => [],

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | Namespaces (and the folders they live in) scanned for ViewModels that
    | carry #[ForView(...)]. Run `php artisan view-model:cache` in production
    | (or `php artisan optimize`) so the scan doesn't happen on every request.
    |
    */

    'discover' => [
        'App\\ViewModels' => app_path('ViewModels'),
    ],

    'cache' => base_path('bootstrap/cache/intermidiate.php'),

    /*
    |--------------------------------------------------------------------------
    | The ViewModel inside the view
    |--------------------------------------------------------------------------
    |
    | Every exported member becomes a view variable. The ViewModel itself is
    | also shared under this name ($vm->lead, $vm->only(...)). Null turns it off.
    |
    */

    'variable' => 'vm',

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
    | A ViewModel returned from a controller answers requests that expect JSON
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
