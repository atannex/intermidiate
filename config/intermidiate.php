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
    | Namespaces (and the folders they live in) scanned for Presenters. Null
    | means the app's own folder: App\Presenters in app/Presenters, under
    | whatever namespace composer.json gives the app. Add module folders as
    | 'Modules\\Blog\\Presenters' => base_path('modules/Blog/Presenters').
    |
    | Run `php artisan presenter:cache` in production (or `php artisan
    | optimize`) so the scan doesn't happen on every request.
    |
    */

    'discover' => null,

    /*
    |--------------------------------------------------------------------------
    | Conventions
    |--------------------------------------------------------------------------
    |
    | A discovered Presenter without #[ForView] serves the view named after
    | its path, when that view exists:
    |
    |     Presenters/ArchivePresenter.php        → archive.blade.php
    |     Presenters/Archive/MonthPresenter.php  → archive/month.blade.php
    |     Presenters/Layouts/AppPresenter.php    → layouts/app.blade.php
    |
    | It's also the view `return ArchivePresenter::make(...)` renders.
    |
    */

    'conventions' => true,

    'cache' => base_path('bootstrap/cache/intermidiate.php'),

    /*
    |--------------------------------------------------------------------------
    | Model presenters
    |--------------------------------------------------------------------------
    |
    | Models a Presenter hands to its view (one, a collection, a paginator or
    | a Group) arrive wrapped in their ModelPresenter, found by name:
    |
    |     App\Models\Post → App\Presenters\Models\PostPresenter
    |
    | or by #[Presents(Post::class)] on the presenter, or by the map below.
    | Models without one pass through untouched. False wraps only members
    | marked #[Present].
    |
    |     'models' => [App\Models\Post::class => App\Presenters\StoryCard::class],
    |
    */

    'present' => true,

    'models' => [],

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
    | with its members (minus closures and #[ViewOnly]) instead of the
    | rendered view. Off by default since 2.0: any visitor can ask for JSON,
    | so turn it on only for Presenters whose members are safe to publish.
    | For a JSON API, use Laravel's API Resources.
    |
    */

    'json' => false,

    /*
    |--------------------------------------------------------------------------
    | Strict presenting
    |--------------------------------------------------------------------------
    |
    | On, model presenters work only on what the application loaded: reading
    | an unloaded relation through one fails before querying, and computed
    | fields run with lazy loading off for the model and the models loaded
    | with it. Off, they lazy load as in 1.x. Serialization isn't affected.
    |
    */

    'strict' => true,

    /*
    |--------------------------------------------------------------------------
    | Legacy serialization
    |--------------------------------------------------------------------------
    |
    | Off (the default), serialized output (a model presenter's toArray(), a
    | Presenter's JSON) holds only what each model presenter's $visible
    | lists, and raw models, closures and plain objects are refused.
    |
    | On, the 1.x rules come back: a model presenter serializes its whole
    | model (every attribute not in $hidden) plus every computed field, and
    | raw models are serialized whole. That can publish columns nobody
    | chose to expose. Only a literal true turns it on.
    |
    */

    'legacy_serialization' => false,

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
    | Dates
    |--------------------------------------------------------------------------
    |
    | Named formats for Format::date($date, 'long'), on top of the built-in
    | short (j M), medium (j M Y), long (j F Y), full (l j F Y), month (M Y),
    | time (H:i) and datetime (j M Y, H:i). Dates are shown in app.timezone,
    | translated to the app locale.
    |
    */

    'dates' => [],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | Used by Meta and @meta. The site name defaults to app.name.
    |
    */

    'seo' => [
        'site' => null,
        'separator' => ' · ',
        'suffix' => true,                   // add the site name to titles that don't already include it
        'title_limit' => 95,
        'description_limit' => 160,
        'twitter' => null,                  // '@handle'
        'locale' => null,                   // og:locale: null the app locale, false to leave it out
    ],

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
