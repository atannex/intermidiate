# atannex/intermidiate

[![Tests](https://github.com/atannex/intermidiate/actions/workflows/tests.yml/badge.svg)](https://github.com/atannex/intermidiate/actions/workflows/tests.yml)

**Presenters for Laravel.** Move data shaping out of Blade and into small, typed PHP classes. That covers splitting a page of stories into a lead, a secondary pair and a ledger, zero-padding calendar parameters, numbering folios across pages, windowing pagers and deciding which menu item is active. All of it is worked out before the view renders, so controllers stay short and views only hold markup.

```php
return ArchivePresenter::make(posts: $posts, year: $year, month: $month);
```

```blade
@if ($lead) <x-story.lead :story="$lead" /> @endif

@foreach ($ledger->entries() as $entry)
    <li><span>{{ $entry->folio }}</span> {{ $entry->title }}</li>
@endforeach

@foreach ($calendar as $month)
    <a href="{{ $month->url }}" @class(['is-current' => $month->current, 'is-empty' => ! $month->filled])>{{ $month->short }}</a>
@endforeach
```

No `@php`, no `str_pad`, no `slice(3)`.

- [Why](#why)
- [Install](#install)
- [Writing a Presenter](#writing-a-presenter)
- [Three ways to use one](#three-ways-to-use-one)
- [Typed inputs](#typed-inputs)
- [Partitions: lead, secondary, ledger](#partitions-lead-secondary-ledger)
- [Numbers and folios](#numbers-and-folios)
- [Calendars](#calendars)
- [Pagers](#pagers)
- [Menus, tabs and active states](#menus-tabs-and-active-states)
- [Model presenters](#model-presenters)
- [Lazy members](#lazy-members)
- [Commands](#commands)
- [Testing](#testing)
- [Configuration](#configuration)

## Why

This is a real `@php` block from the top of a news archive view:

```blade
@php
    $stories = $posts->getCollection()->values();
    $lead = $stories->count() >= 3 ? $stories->first() : null;
    $beside = $lead ? $stories->slice(1, 2)->values() : collect();
    $ledger = $lead ? $stories->slice(3)->values() : $stories;
    $ledgerStart = ($posts->firstItem() ?? 1) + ($lead ? 3 : 0);
    $period = $monthName ? $monthName.' '.$year : $year;
    $calendar = collect(config('dates.months'))->map(fn ($name, $number) => [
        'name' => $name,
        'short' => Str::limit($name, 3, ''),
        'param' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        'current' => $number === $month,
        'filled' => in_array(str_pad((string) $number, 2, '0', STR_PAD_LEFT), $monthsWithPosts, true),
    ])->values();
@endphp
```

It works, but it has the usual problems:

| Problem | What a Presenter changes |
| --- | --- |
| Logic is untestable unless you render HTML and grep it | It's a plain class: `ArchivePresenter::make(...)->lead` in a unit test |
| No types: a `"2026"` route parameter, a `null` month and an array of strings all just flow through | Constructor parameters are typed and checked. Strings are converted (`"03"` → `3`, `"sports"` → `Desk::Sports`), and a bad value fails with the parameter's name |
| Off-by-one numbering on page 2 (`$ledgerStart = …`) | Partitions keep every item's number on the whole listing |
| `str_pad` repeated in every view | Every calendar, folio and pager value comes already padded |
| A typo like `$leger` renders nothing in silence | `$presenter->leger` throws: *has no "leger". Did you mean "ledger"?* |
| The Blade formatter mangles PHP inside attributes and `@php` blocks | There's no PHP left to mangle |
| The same slicing is copied between the archive, tag and author pages | One Presenter, or one `Partition` call, shared by all of them |

## Install

```bash
composer require atannex/intermidiate
```

PHP 8.2+, Laravel 11 or 12. The service provider and the `Intermidiate` facade register themselves.

```bash
php artisan vendor:publish --tag=intermidiate-config   # optional
php artisan vendor:publish --tag=intermidiate-stubs    # optional, to customise make:presenter
```

## Writing a Presenter

```bash
php artisan make:presenter ArchivePresenter --view=date
```

```php
namespace App\Presenters;

use App\Models\Post;
use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Calendar\Calendar;
use Atannex\Intermidiate\Calendar\Month;
use Atannex\Intermidiate\Pagination\Pages;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Slicing\Partitioned;
use Atannex\Intermidiate\Presenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[ForView('date')]
final class ArchivePresenter extends Presenter
{
    public function __construct(
        public readonly LengthAwarePaginator $posts,
        public readonly int $year,
        public readonly ?int $month = null,
        private readonly array $monthsWithPosts = [],
    ) {}

    public function lead(): ?Post
    {
        return $this->layout()->lead->first();
    }

    public function beside(): Group
    {
        return $this->layout()->beside;
    }

    public function ledger(): Group
    {
        return $this->layout()->ledger;
    }

    public function period(): string
    {
        return $this->month ? Calendar::year($this->year)->find($this->month)->label() : (string) $this->year;
    }

    /** @return Collection<int, Month> */
    public function calendar(): Collection
    {
        return Calendar::year($this->year)
            ->current($this->month)
            ->filled($this->monthsWithPosts)
            ->link(fn (Month $month) => route('archive.month', $month->routeParameters()))
            ->months();
    }

    public function pages(): Pages
    {
        return Pages::of($this->posts);
    }

    public function emptyTitle(): string
    {
        return __('Nothing filed in :period', ['period' => $this->period]);
    }

    private function layout(): Partitioned
    {
        return $this->once('layout', fn () => $this->partition($this->posts)
            ->take('lead', 1)
            ->take('beside', 2)
            ->rest('ledger')
            ->atLeast(3)
            ->get());
    }
}
```

**What the view receives:**

| Member | Becomes |
| --- | --- |
| public property | `$posts`, `$year`, `$month` |
| public method without parameters | its return value: `$lead`, `$ledger`, `$calendar`. It runs once, however often you read it |
| public method with parameters | a closure: `{{ $heading('today') }}` |
| the Presenter itself | `$presenter`, so `$presenter->lead` and `@include('partial', $presenter->only('lead', 'beside'))` work |
| protected / private members | nothing, so they're safe for helpers like `layout()` |

Attributes refine the export:

```php
#[Hidden]          // a public member the view doesn't get
#[Expose('stories')] // exported under another name
#[ViewOnly]        // in the view, but left out of the JSON
#[Lazy]            // runs only if the view uses it (see below)
```

Inside the class, read members as properties to reuse the memoized value: `$this->period` runs `period()` once, while `$this->period()` runs it again. When an input and a member share a name (a private `$stories` input and a public `stories()` member), `$this->stories` reads the input, so route shared work through `$this->once('key', fn () => …)` instead. Override `prepare()` for set-up that should run once before the first member is read (eager loading, for example).

The base class reserves the names of its own methods (`make`, `from`, `view`, `with`, `only`, `except`, `get`, `has`, `toArray`, `render`, `prepare`, `once`…), so don't use them for members. To export a variable with one of those names, give the method another name and add `#[Expose('from')]`. Redeclaring `make()` or `from()` without `static` is a PHP fatal error, and with discovery on it breaks every page, so the mistake shows up on the first request.

## Three ways to use one

**1. Return it from the controller.** It's `Responsable`:

```php
public function __invoke(int $year, ?int $month = null)
{
    return ArchivePresenter::make(posts: $this->archive->page($year, $month), year: $year, month: $month);
        // ->view('date') when there's no #[ForView], or to pick another view
        // ->with('extra', $value) for one-off variables
}
```

When the request expects JSON, the same Presenter answers with its members as JSON: closures and `#[ViewOnly]` members are left out, and paginators, groups, calendars and menus serialize themselves. One class can serve the page and the API. Set `json` to `false` to turn this off.

**2. Bind it to a view and leave the controller alone.** With `#[ForView('date')]` (found by discovery) or the config:

```php
// config/intermidiate.php
'bindings' => [
    'date' => App\Presenters\ArchivePresenter::class,
    'desks.*' => App\Presenters\DeskPresenter::class,
],
```

The controller keeps passing raw data with `return view('date', compact('posts', 'year', 'month', 'monthsWithPosts'))`. Before the view renders, the Presenter is built from that data (`months_with_posts` fills `$monthsWithPosts` too) and its members are added. Shared view data (`$errors`, anything from `View::share()`) is included too. If the controller already passed the Presenter, the binding steps aside.

Or bind it in code: `Intermidiate::bind('date', ArchivePresenter::class)`.

**3. Inline, for partials and anonymous components that have no controller:**

```blade
@presenter(App\Presenters\BylinePresenter::class)
{{-- or with extra input: @presenter(App\Presenters\BylinePresenter::class, ['compact' => true]) --}}

<p>{{ $byline }} · {{ $count }}</p>
```

The Presenter is built from the variables in scope, and its members become variables.

## Typed inputs

`make()` takes named (or positional) arguments. `from()` takes loose data such as view variables, a request or an array: keys may be snake_case or camelCase, and extra keys are ignored.

Each constructor parameter is filled from the data, then from the container (for services, including optional ones the container has bound), then from its default, then with `null` when it's nullable. In loose data, a variable that only shares a service parameter's name (a page's `$navigation` array reaching a layout presenter's `Navigation $navigation`) is ignored and the service comes from the container; models, enums, dates and collections are never swapped like that, and a wrong value for them still fails. Values are checked against the declared type, and with `coerce` on (the default), loose input is converted:

| Declared | Accepts |
| --- | --- |
| `int` / `float` | `"2026"`, `"03"`, `"1.5"` |
| `bool` | `"1"`, `"true"`, `"on"`, `"no"`… |
| `string` | numbers, `Stringable`, backed enums |
| a backed enum | its value: `"sports"` → `Desk::Sports` |
| `Carbon` / `CarbonImmutable` / `DateTimeInterface` | `"2026-03-01"`, timestamps |
| `Collection` | arrays |
| `array` | `Arrayable` |

Mistakes fail early and say what went wrong:

```
ArchivePresenter expects $year to be int, got string "twenty".
ArchivePresenter needs $posts (LengthAwarePaginator), but the data has no "posts". Did you mean "post"? Available: $post, $year.
DeskPresenter has no constructor parameter $paeg. Did you mean "page"? It takes: $desk, $page.
```

## Partitions: lead, secondary, ledger

```php
use Atannex\Intermidiate\Slicing\Partition;

$layout = Partition::of($posts)               // paginator, cursor paginator, collection, array
    ->take('lead', 1, fn (Post $post) => $post->hasMedia('image'))  // the first story with a picture
    ->take('beside', 2)
    ->take('wire', 6)
    ->rest('ledger')
    ->atLeast(3)                              // fewer than 3 stories? all go to the ledger
    ->when($posts->onFirstPage())             // the lead layout opens page one only
    ->unique('id')                            // drop repeats first
    ->get();

$layout->lead->first();     // or $layout['lead'], $layout->group('lead')
$layout->has('lead');       // exists and isn't empty
$layout->applied();         // false when a condition sent everything to the fallback
$layout->remainder();       // what no group took, when there's no rest()
```

A group with a test takes the first items that pass it, wherever they are. The other items keep their order.

**Numbers survive the cut.** Every item keeps its position on the whole listing, so the ledger of page 3 (20 a page) starts at 44, not 1:

```php
$ledger = $layout->ledger;
$ledger->start();        // 44
$ledger->range();        // "44–60"
$ledger->folio(0);       // "44" (padded: "04" on page one)
$ledger->numberAt(2);    // 46

foreach ($ledger->entries() as $entry) {
    $entry->item;         // the story ($entry->title reads from it too)
    $entry->number;       // 44
    $entry->folio;        // "44"
    $entry->first(); $entry->last(); $entry->odd(); $entry->even();
}

$ledger->columns(3);     // three balanced Groups, numbers kept
$ledger->chunk(4);       // Groups of four
$ledger->filter(fn ($p) => $p->is_live)->take(5)->skip(1);   // still Groups, numbers kept
$ledger->pluck('title'); // any other Collection method passes through
```

Groups are `Countable`, iterable, `Arrayable` and `JsonSerializable`, so `@foreach ($ledger as $post)` and `@if ($ledger->isNotEmpty())` work too.

## Numbers and folios

```php
use Atannex\Intermidiate\Support\Numeral;

Numeral::pad(3);                 // "03" (width from config `pad`)
Numeral::pad(7, 3);              // "007"
Numeral::fraction(3, 12);        // "03 / 12"; widens to the total: "003 / 120"
Numeral::ordinal(22);            // "22nd" (localised with intl)
Numeral::roman(2026);            // "MMXXVI"
Numeral::short(1250);            // "1.3K"
Numeral::percent(1, 3, 1);       // 33.3
Numeral::bar(30, 120, minimum: 4); // "25%", a CSS width that never vanishes below 4%
```

## Calendars

```php
use Atannex\Intermidiate\Calendar\Calendar;

$year = Calendar::year(2026)
    ->current($month)                         // 3, "03" or null
    ->filled($monthsWithPosts)                // ["01", "03"], [1, 3], ["2026-03"], dates, or [3 => 12] counts
    ->link(fn (Month $m) => route('archive.month', $m->routeParameters()))  // empty months stay unlinked (pass empty: true to link them)
    ->locale('fr')                            // defaults to the app locale
    ->names(config('dates.months'));          // optional: your own names

foreach ($year as $month) {
    $month->param;      // "03", ready for routes and query strings
    $month->name;       // "March" / "mars"
    $month->short;      // "Mar"
    $month->current;    // the month being viewed
    $month->filled;     // has content
    $month->count;      // how much, when you passed counts
    $month->url;
    $month->key();      // "2026-03"
    $month->label();    // "March 2026"
    $month->routeParameters();  // ['year' => 2026, 'month' => '03']
    $month->isPast(); $month->isFuture(); $month->isThisMonth();
}

$year->filledCount(); $year->total(); $year->selected(); $year->previous(); $year->next();
```

A month as a grid:

```php
$grid = Calendar::month(2026, 3)            // or Calendar::at($date)
    ->current(7)
    ->filled($publishedDays)                  // [7, "09", "2026-03-20", …] or day => count
    ->link(fn (Day $day) => route('archive.day', $day->routeParameters()))
    ->weekStartsOn(0);                        // Sunday; default from config (Monday)

$grid->weekdays();   // headings in the right order: ->name, ->short, ->min, ->weekend
$grid->weeks();      // Weeks of 7 Days, padded with the neighbouring months' days
$grid->days();       // just this month's days
$grid->previous(); $grid->next(); $grid->month();
```

Each `Day` has `number`, `param` ("07"), `inMonth`, `current`, `today`, `weekend`, `filled`, `count`, `url`, `weekday`, `key()` and `routeParameters()`. Call `->withoutOutsideDays()` to get `null` cells instead of the neighbouring months' days.

## Pagers

```php
use Atannex\Intermidiate\Pagination\Pages;

$pages = Pages::of($posts, window: 2);

$pages->fraction();     // "02 / 05"
$pages->folio();        // "02"
$pages->range();        // "21–40"
$pages->current(); $pages->last(); $pages->total(); $pages->from(); $pages->to();
$pages->previousUrl(); $pages->nextUrl(); $pages->onFirstPage(); $pages->onLastPage(); $pages->hasPages();
```

```blade
<nav>
    @foreach ($pages as $link)
        @if ($link->gap)
            <span>…</span>
        @else
            <a href="{{ $link->url }}" aria-current="{{ $link->ariaCurrent() }}">{{ $link->folio }}</a>
        @endif
    @endforeach
</nav>
```

The links are the first and last page, the window around the current page, and a gap wherever pages are skipped (a single skipped page is shown rather than replaced by a gap): `01 … 08 09 [10] 11 12 … 20`. Page numbers widen to the last page (`007` of `500`). Simple paginators show previous, current and next; cursor paginators expose only the previous and next links.

## Menus, tabs and active states

```php
use Atannex\Intermidiate\Navigation\Menu;

$menu = Menu::make()
    ->add('home', __('Home'), route('home'))
    ->add('archive', __('Archive'), route('archive.index'), activeOn: 'archive.*')
    ->add('desks', __('Desks'), route('desks.index'), children: [
        ['key' => 'sports', 'label' => __('Sports'), 'url' => route('desks.show', 'sports')],
    ])
    ->sections();       // "/archive" stays active on "/archive/2026/03"

// or from rows: Menu::from(config('desks'), fn ($desk, $key) => Item::make($key, $desk['label'], route('desks.show', $key)));
```

The menu reads the current request. Only the **most specific** match becomes active: an exact URL (query parameters included) beats a route or path pattern, which beats a section prefix. Parents of the active item are marked as its `trail`.

| `activeOn` pattern | Matches |
| --- | --- |
| `'archive.*'` or `'route:archive.*'` | route names |
| `'/archive/*'` or `'path:archive/*'` | paths |
| `'url:https://…'` | a full URL |
| `fn (Request $request, Item $item) => …` | anything |

Other options are `->multiple()` (several active), `->force('archive')` and `->for($request)`. Read the result with `$menu->active()`, `$menu->activeKey()`, `$menu->trail()` (breadcrumbs) and `$menu->find('key')`.

```blade
@foreach ($menu as $item)
    <a href="{{ $item->url }}" aria-current="{{ $item->current() }}" class="{{ $item->classes('nav__link', 'is-active') }}">{{ $item->label }}</a>
    {{-- or: <a {{ $item->attributes(['class' => 'nav__link']) }}>{{ $item->label }}</a> --}}
@endforeach
```

`aria-current` is `"page"` on the active item. On the others it's `null`, which Blade renders as `aria-current=""`; use `$item->attributes()` to leave the attribute out entirely.

**Tabs** from a query parameter, validated:

```php
use Atannex\Intermidiate\Navigation\Tabs;

$tabs = Tabs::query('sort', ['latest' => __('Latest'), 'popular' => __('Most read')]);
$tabs = Tabs::query('desk', Desk::class, default: Desk::News);   // a backed enum: labels from label() or the case name

$tabs->selected();   // "popular", or Desk::Sports for enums. An unknown ?sort=<script> falls back to the default
$tabs->is('popular'); $tabs->label(); $tabs->isDefault();
```

Each tab's URL is the current URL with its value. The default tab drops the parameter for clean URLs, and `?page` is reset (pass `keepPage: true` to keep it).

## Model presenters

Presentation logic for a single model, used inside a Presenter:

```bash
php artisan make:model-presenter StoryCard
```

```php
final class StoryCard extends ModelPresenter
{
    public function kicker(): string
    {
        return Str::upper($this->category?->name ?? 'News');   // $this->x reads the model
    }

    public function readingTime(): string
    {
        return trans_choice(':count min read', $this->minutes);
    }
}
```

```php
public function cards(): Collection
{
    return $this->present($this->posts, StoryCard::class);    // or StoryCard::collection($posts)
}
```

```blade
@foreach ($cards as $card)
    <small>{{ $card->kicker }}</small>  {{ $card->title }}  {{ $card->reading_time }}
@endforeach
```

Computed fields run once each. Everything else (properties, relations, methods) is forwarded to the model, and `$card->unwrap()` returns the model itself. `toArray()` and JSON return the model's array form plus the computed fields.

## Lazy members

```php
#[Lazy]
public function related(): Collection
{
    return Post::related($this->post)->take(6)->get();   // only queried if the view shows it
}
```

The view gets a proxy that resolves on first use: when you echo it, loop over it, count it or read a property or method through it. An object is always truthy, so test it with one of the value's methods (`@if ($related->isNotEmpty())`) or unwrap it (`$related->value()`). Reading `$presenter->related` returns the value itself.

## Commands

```bash
php artisan make:presenter ArchivePresenter --view=date   # app/Presenters, with #[ForView('date')]
php artisan make:model-presenter StoryCard                 # app/Presenters/Models
php artisan presenter:list                                # every bound Presenter, its views and variables with types
php artisan presenter:list ArchivePresenter --json
php artisan presenter:cache                               # cache discovery (also run by `php artisan optimize`)
php artisan presenter:clear
```

```
App\Presenters\ArchivePresenter ........................................ date
  $posts .......................... Illuminate\Pagination\LengthAwarePaginator
  $lead ............................................................ ?App\Models\Post
  $ledger ...................................... Atannex\Intermidiate\Slicing\Group
  $calendar ............................................ Illuminate\Support\Collection
```

## Testing

A Presenter is a plain object, so test it without rendering:

```php
$presenter = ArchivePresenter::make(posts: $paginator, year: 2026, month: 3);

expect($presenter->lead->id)->toBe(11);
expect($presenter->ledger->folio(0))->toBe('14');
expect($presenter->calendar[2]->current)->toBeTrue();
```

To check a response:

```php
$this->get('/archive/2026')
    ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->lead !== null);
```

This works whether the controller returned the Presenter, a binding built it, or the response was JSON.

## Configuration

```php
return [
    'bindings' => [],                                   // view => Presenter class(es); wildcards allowed
    'discover' => ['App\\Presenters' => app_path('Presenters')],  // scanned for #[ForView]
    'cache' => base_path('bootstrap/cache/intermidiate.php'),
    'variable' => 'presenter',                          // the Presenter inside its view; null turns it off
    'coerce' => true,                                   // convert strings to the declared types
    'json' => true,                                     // answer JSON requests with the members
    'pad' => 2,                                         // default folio width
    'week_starts_on' => 1,                              // 0 Sunday … 6 Saturday
];
```

Bindings register the first time the view factory is resolved, so requests that render no view never scan for Presenters.

## License

MIT. See [LICENSE](LICENSE).
