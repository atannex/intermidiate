<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Cached;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

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
