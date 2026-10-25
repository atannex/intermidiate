<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Facades\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\BylinePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\DeskPresenter;
use Atannex\Intermidiate\Tests\Fixtures\SharedPresenter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

final class BindingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // The controller passes raw data; #[ForView('archive')] does the rest.
        Route::get('/raw/{year}', fn (string $year) => view('archive', [
            'posts' => $this->stories(3),
            'year' => $year,
            'months_with_posts' => ['01'],
        ]));

        Route::get('/author', fn () => view('author', ['name' => 'Ngwa']));
    }

    public function test_a_bound_view_gets_its_presenter_from_its_own_data(): void
    {
        $this->get('/raw/2026')
            ->assertOk()
            ->assertSee('<article class="lead">Story 1</article>', false)
            ->assertSee('<a href="/archive/2026/01" class="">Jan</a>', false)
            ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->year === 2026);
    }

    public function test_discovery_finds_attributes_and_conventions_and_skips_abstract_classes(): void
    {
        $this->assertSame([
            ArchivePresenter::class => ['archive'],
            BylinePresenter::class => ['byline'],
            DeskPresenter::class => ['desk'],
        ], Intermidiate::discovered());

        $this->assertArrayHasKey(DeskPresenter::class, Intermidiate::bindings());
    }

    public function test_a_presenter_named_after_a_view_is_bound_to_it_with_no_attribute_or_config(): void
    {
        $html = View::make('desk', ['desk' => 'economy', 'page' => '5'])->render();

        $this->assertSame('<h1>Economy desk</h1><p>Page 5 Economy desk today</p>', trim($html));
    }

    public function test_conventions_can_be_turned_off(): void
    {
        $intermidiate = new \Atannex\Intermidiate\Intermidiate(fn () => $this->app['view'], Intermidiate::namespaces(), conventions: false);

        $this->assertSame([ArchivePresenter::class => ['archive']], $intermidiate->discovered());
        $this->assertNull($intermidiate->conventionalView(DeskPresenter::class));
    }

    public function test_bindings_from_code_and_wildcards(): void
    {
        DeskPresenter::$prepared = 0;
        Intermidiate::bind('des*', DeskPresenter::class);

        $html = View::make('desk', ['desk' => 'economy', 'page' => '5'])->render();

        $this->assertSame('<h1>Economy desk</h1><p>Page 5 Economy desk today</p>', trim($html));
        $this->assertSame(1, DeskPresenter::$prepared, 'Bound by convention and by wildcard, it is still built once.');
    }

    public function test_a_view_that_already_has_the_presenter_is_left_alone(): void
    {
        $presenter = ArchivePresenter::make(posts: $this->stories(3), year: 2027);

        $html = View::make('archive', $presenter->toArray())->render();

        $this->assertStringContainsString('<h1>2027</h1>', $html);
    }

    public function test_bound_presenters_see_shared_data_and_ignore_clashing_names(): void
    {
        View::share('sharedTitle', 'Shared');
        Intermidiate::bind('shared', SharedPresenter::class);

        // $navigation here is page data, not the Clockwork service the presenter asks for.
        $html = View::make('shared', ['navigation' => ['home', 'news']])->render();

        $this->assertSame('<p>Shared tick</p>', trim($html));
    }

    public function test_only_presenters_can_be_bound(): void
    {
        $this->expectExceptionMessage("doesn't extend");

        Intermidiate::bind('x', \stdClass::class);
    }

    public function test_the_presenter_directive_builds_one_inline(): void
    {
        $this->get('/author')->assertOk()->assertSee('<p>By Ngwa · 07</p>', false);
    }
}
