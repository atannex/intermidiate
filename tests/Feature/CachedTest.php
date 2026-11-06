<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Cached;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Support\Fingerprint;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;

final class CachedTest extends TestCase
{
    public function test_a_cached_member_is_computed_once_across_requests_per_input_and_locale(): void
    {
        TrendingPresenter::$runs = 0;

        $this->assertSame('sports:1', TrendingPresenter::make(desk: Desk::Sports)->trending);
        $this->assertSame('sports:1', TrendingPresenter::make(desk: Desk::Sports)->trending, 'a new instance reads the cache');
        $this->assertSame(1, TrendingPresenter::$runs);

        $this->assertSame('news:2', TrendingPresenter::make(desk: Desk::News)->trending, 'other inputs, other key');

        app()->setLocale('fr');
        TrendingPresenter::make(desk: Desk::Sports)->trending;
        $this->assertSame(3, TrendingPresenter::$runs, 'other locale, other key');

        Cache::flush();
        TrendingPresenter::make(desk: Desk::Sports)->trending;
        $this->assertSame(4, TrendingPresenter::$runs);
    }

    public function test_value_objects_that_differ_only_in_private_state_get_their_own_key(): void
    {
        FilteredPresenter::$runs = 0;

        $this->assertSame('rain', FilteredPresenter::make(filters: new Filters('rain'))->summary);
        $this->assertSame('sun', FilteredPresenter::make(filters: new Filters('sun'))->summary);
        $this->assertSame('rain', FilteredPresenter::make(filters: new Filters('rain'))->summary);
        $this->assertSame(2, FilteredPresenter::$runs);
    }

    public function test_cache_scope_keeps_tenants_apart(): void
    {
        TenantPresenter::$runs = 0;

        TenantPresenter::$tenant = 'bamenda';
        $this->assertSame('bamenda', TenantPresenter::make()->name);
        TenantPresenter::$tenant = 'buea';
        $this->assertSame('buea', TenantPresenter::make()->name);
        TenantPresenter::$tenant = 'bamenda';
        $this->assertSame('bamenda', TenantPresenter::make()->name);
        $this->assertSame(2, TenantPresenter::$runs);
    }

    public function test_a_model_presenter_caches_per_model_and_edit(): void
    {
        CachedCard::$runs = 0;
        $story = new Story(4, 'Rain');

        $this->assertSame('RAIN', CachedCard::make($story)->loud);
        $this->assertSame('RAIN', CachedCard::make(new Story(4, 'Rain'))->loud);
        $this->assertSame('SUN', CachedCard::make(new Story(5, 'Sun'))->loud);
        $this->assertSame(2, CachedCard::$runs);
    }

    public function test_keys_are_stable_across_instances_and_independent_of_object_identity(): void
    {
        $one = Fingerprint::of(FilteredPresenter::make(filters: new Filters('rain')));
        $two = Fingerprint::of(FilteredPresenter::make(filters: new Filters('rain')));

        $this->assertNotNull($one);
        $this->assertSame($one, $two);
        $this->assertNotSame($one, Fingerprint::of(FilteredPresenter::make(filters: new Filters('sun'))));
    }

    public function test_a_mutated_input_gets_a_new_key(): void
    {
        MutablePresenter::$runs = 0;
        $query = new SearchQuery('rain');
        $presenter = MutablePresenter::make(query: $query);

        $this->assertSame('rain', $presenter->results);
        $query->term = 'sun';
        $this->assertSame('sun', MutablePresenter::make(query: $query)->results);
        $this->assertSame(2, MutablePresenter::$runs);
    }

    public function test_inputs_without_a_reliable_identity_are_never_cached(): void
    {
        UnidentifiedPresenter::$runs = 0;
        $lazy = LazyCollection::make(function () {
            UnidentifiedPresenter::$iterated = true;

            yield 1;
        });

        foreach ([fn () => 'a', fn () => 'b'] as $closure) {
            UnidentifiedPresenter::make(input: $closure)->value;
        }
        UnidentifiedPresenter::make(input: $lazy)->value;
        UnidentifiedPresenter::make(input: $lazy)->value;

        $this->assertSame(4, UnidentifiedPresenter::$runs, 'computed fresh every time');
        $this->assertFalse(UnidentifiedPresenter::$iterated, 'a lazy collection is never iterated to build a key');
        $this->assertNull(Fingerprint::of(UnidentifiedPresenter::make(input: new Article(['title' => 'unsaved']))));
    }

    public function test_tags_flush_cached_members(): void
    {
        TaggedPresenter::$runs = 0;

        TaggedPresenter::make()->value;
        TaggedPresenter::make()->value;
        Cache::tags(['news'])->flush();
        TaggedPresenter::make()->value;

        $this->assertSame(2, TaggedPresenter::$runs);
    }

    public function test_cached_methods_with_parameters_are_refused(): void
    {
        $this->expectException(PresenterException::class);
        $this->expectExceptionMessage('can\'t be #[Cached]: it takes parameters');

        BadCachePresenter::make()->toArray();
    }
}

final class SearchQuery
{
    public function __construct(public string $term) {}
}

final class MutablePresenter extends Presenter
{
    public static int $runs = 0;

    public function __construct(public readonly SearchQuery $query) {}

    #[Cached(minutes: 5, shared: true)]
    public function results(): string
    {
        self::$runs++;

        return $this->query->term;
    }
}

final class UnidentifiedPresenter extends Presenter
{
    public static int $runs = 0;

    public static bool $iterated = false;

    public function __construct(public readonly mixed $input) {}

    #[Cached(minutes: 5, shared: true)]
    public function value(): int
    {
        return ++self::$runs;
    }
}

final class TaggedPresenter extends Presenter
{
    public static int $runs = 0;

    #[Cached(minutes: 5, tags: ['news'], shared: true)]
    public function value(): int
    {
        return ++self::$runs;
    }
}

final class Filters
{
    public function __construct(private readonly string $term) {}

    public function term(): string
    {
        return $this->term;
    }
}

final class FilteredPresenter extends Presenter
{
    public static int $runs = 0;

    public function __construct(public readonly Filters $filters) {}

    #[Cached(minutes: 5)]
    public function summary(): string
    {
        self::$runs++;

        return $this->filters->term();
    }
}

final class TenantPresenter extends Presenter
{
    public static int $runs = 0;

    public static string $tenant = '';

    #[Cached(minutes: 5, shared: true)]
    public function name(): string
    {
        self::$runs++;

        return self::$tenant;
    }

    protected function cacheScope(): array
    {
        return ['tenant' => self::$tenant];
    }
}

/** @extends ModelPresenter<Story> */
final class CachedCard extends ModelPresenter
{
    public static int $runs = 0;

    #[Cached(minutes: 5, shared: true)]
    public function loud(): string
    {
        self::$runs++;

        return strtoupper($this->subject->title);
    }
}

final class BadCachePresenter extends Presenter
{
    #[Cached]
    public function label(string $prefix): string
    {
        return $prefix;
    }
}

final class TrendingPresenter extends Presenter
{
    public static int $runs = 0;

    public function __construct(public readonly Desk $desk) {}

    #[Cached(minutes: 5)]
    public function trending(): string
    {
        return $this->desk->value.':'.++self::$runs;
    }
}
