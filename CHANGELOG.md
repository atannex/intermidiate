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
- `ModelPresenter`: computed fields over a model, forwarding everything else.
- `Numeral`: pad, fraction, ordinal, roman, short, percent, bar.
- Commands: `make:presenter`, `make:model-presenter`, `presenter:list`, `presenter:cache`, `presenter:clear`. `TestResponse::assertPresenter()`.
