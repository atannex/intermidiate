<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Facades\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\DeskPresenter;
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

    public function test_a_bound_view_gets_its_view_model_from_its_own_data(): void
    {
        $this->get('/raw/2026')
            ->assertOk()
            ->assertSee('<article class="lead">Story 1</article>', false)
            ->assertSee('<a href="/archive/2026/01" class="">Jan</a>', false)
            ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->year === 2026);
    }

    public function test_discovery_finds_for_view_attributes_and_skips_abstract_classes(): void
    {
        $this->assertSame([ArchivePresenter::class => ['archive']], Intermidiate::discovered());
        $this->assertArrayHasKey(ArchivePresenter::class, Intermidiate::bindings());
    }

    public function test_bindings_from_code_and_wildcards(): void
    {
        Intermidiate::bind('des*', DeskPresenter::class);

        $html = View::make('desk', ['desk' => 'economy', 'page' => '5'])->render();

        $this->assertSame('<h1>Economy desk</h1><p>Page 5 Economy desk today</p>', trim($html));
    }

    public function test_a_view_that_already_has_the_view_model_is_left_alone(): void
    {
        $presenter = ArchivePresenter::make(posts: $this->stories(3), year: 2027);

        $html = View::make('archive', $presenter->toArray())->render();

        $this->assertStringContainsString('<h1>2027</h1>', $html);
    }

    public function test_only_view_models_can_be_bound(): void
    {
        $this->expectExceptionMessage("doesn't extend");

        Intermidiate::bind('x', \stdClass::class);
    }

    public function test_the_presenter_directive_builds_one_inline(): void
    {
        $this->get('/author')->assertOk()->assertSee('<p>By Ngwa · 07</p>', false);
    }
}
