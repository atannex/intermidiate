# Changelog

## v1.0.0 (unreleased)

- `Presenter` base: typed constructor inputs; public properties and methods as view variables (memoized; methods with parameters as closures); `#[Hidden]`, `#[Expose]`, `#[ViewOnly]`, `#[Lazy]`, `#[ForView]`; `Responsable` with JSON for `expectsJson()` requests; `only()`, `except()`, `with()`, `prepare()`, `once()`.
- Input hydration: named, positional or loose (snake/camel) data; container services; coercion of strings to int, float, bool, backed enums, dates and collections; errors that name the parameter and suggest close matches.
- View bindings by attribute, config or `Intermidiate::bind()`, with discovery, a discovery cache and `optimize` integration; the `@presenter` directive for partials.
- `Partition` / `Group` / `Entry`: named groups with tests, conditions, fallbacks, dedupe, and numbering that follows the paginator; columns, chunks and folios.
- `Calendar`: year and month calendars with padded parameters, translated names, filled/current/today flags, counts, links and route parameters.
- `Pages`: windowed pager links with gaps, padded page numbers, fractions and ranges.
- `Menu` / `Item` / `Tabs`: active state from the request (exact URL > pattern > section), trails, classes and attributes; enum-backed, validated query tabs.
- View bindings include shared view data (`$errors`, `View::share()`); a view variable that only shares a service parameter's name no longer breaks the presenter.
- Conventions: Presenters need no registration. Any Presenter in `app/Presenters` (the namespace is read from the app) serves the view its path names (`Archive/MonthPresenter` → `archive.month`) and renders it by default. Convention bindings step aside when a view lacks the Presenter's inputs. `make:presenter archive.month` accepts view names, adds the suffix, skips `#[ForView]` when the name already says it and creates the missing Blade file. Config `discover` defaults to `null` (auto) and `conventions` can be turned off.
- Automatic model presenting: models a Presenter hands its view (single, collections, paginators, Groups) are wrapped in their `ModelPresenter`, found by config/`Intermidiate::present()`, `#[Presents]`, the name (`Post` → `Presenters\Models\PostPresenter`) or a parent class. Paginators keep links and totals, Groups keep numbers (`Group::map()` now keeps them too). `#[Raw]` and `#[Present(Class)]` per member, config `present`/`models`. `$this->present($items)` finds the presenter itself. Presented models are `UrlRoutable` and unwrap into model-typed Presenter parameters. `make:model-presenter Post` adds the suffix; `--model` writes `#[Presents]`.
- Pages without controllers: `Route::presenter($uri, Presenter::class, view: null)`. Inputs fill themselves from route parameters (models looked up by route key or `{post:slug}`, 404 when missing; a path value that doesn't fit is a 404) and from the query string for `#[Query]` parameters (bad values fall back to the default). Applies to bound views, `make()`, `from()` and `@presenter` too.
- A Presenter is an invokable controller: `Route::get('/about', AboutPresenter::class)` or an attribute router's `#[Get]` on the class. Each request builds it from the route; a static `source()` (route parameters and container injected, models by route key, `null` for a 404) supplies the data, whose keys also reach the view.
- `Format::date()`/`iso()`/`relative()` take a timezone, `date(translate: false)` keeps names untranslated, `relative(days: null)` stays relative, `count()` groups thousands, `Format::number()`, `initials(edges:, lettersOnly:)`.
- `#[Paginate]` and `#[Fetch]` fill constructor parameters with models: the model from the type or the name (`$posts` → `App\Models\Post`), the result shape from the type (length-aware, simple or cursor pagination; a collection; the first model), `with`, `withCount`, `latest`, `oldest`, `scopes`, `perPage`, `pageName`, `limit`, and a static `{parameter}Query()` that narrows the query with the other inputs. Pager links keep the query string. Passed data wins and runs no query.
- Inputs that fill themselves: `#[Config]`, `#[Old]`, `#[Session]`, `#[CurrentUser]`, and by name a user-typed `$user`/`$viewer`/`$currentUser`, `string $locale`, `string $timezone`.
- `#[Cached]` members, keyed by class, member, locale and a fingerprint of the inputs (models by key and `updated_at`).
- `Format`: excerpt, text, date (named formats, timezone, locale), relative, iso, count, initials, readingTime.
- SEO: `Seo\Meta` (title with site suffix, description, canonical, robots, Open Graph, Twitter, article) and `Seo\JsonLd` (article, breadcrumbs, organization, website, safe encoding); `@meta` reads a `meta()` member or guesses from variables and sections.
- Bare `@presenter` finds the view's own Presenter; `presenter:tidy [--fix] [--attributes]` rewrites wiring the conventions already cover.
- `ModelPresenter`: computed fields over a model, forwarding everything else.
- `Numeral`: pad, fraction, ordinal, roman, short, percent, bar.
- Commands: `make:presenter`, `make:model-presenter`, `presenter:list`, `presenter:cache`, `presenter:clear`. `TestResponse::assertPresenter()`.
