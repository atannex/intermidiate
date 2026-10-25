<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\DeskPresenter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class ResponseTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/archive/{year}', fn (string $year) => ArchivePresenter::make(
            posts: $this->stories(25, page: (int) request('page', 1)),
            year: $year,
            monthsWithPosts: ['01', '03'],
        ));

        Route::get('/desks/{desk}', fn (string $desk) => DeskPresenter::from(['desk' => $desk, 'page' => request('page', 1)])->view('desk'));
    }

    public function test_a_returned_presenter_renders_its_view(): void
    {
        $response = $this->get('/archive/2026?page=2');

        $response->assertOk()
            ->assertSee('<h1>2026</h1>', false)
            ->assertSee('<article class="lead">Story 11</article>', false)
            ->assertSee('<article class="beside">12 Story 12</article>', false)
            ->assertSee('<li class="ledger">14 Story 14</li>', false)
            ->assertSee('<a href="/archive/2026/01" class="">Jan</a>', false)
            ->assertSee('<a href="" class="is-empty">Feb</a>', false)
            ->assertSee('<p class="pages">02 / 03</p>', false)
            ->assertSee('<p class="presenter">2026</p>', false)
            ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->lead->id === 11);
    }

    public function test_the_same_presenter_answers_json(): void
    {
        $this->getJson('/desks/sports?page=2')
            ->assertOk()
            ->assertJsonPath('title', 'Sports desk')
            ->assertJsonPath('pageLabel', 'Page 2')
            ->assertJsonPath('desk', 'sports')
            ->assertJsonMissingPath('heading')
            ->assertJsonMissingPath('viewOnly')
            ->assertPresenter(DeskPresenter::class);
    }

    public function test_json_can_be_turned_off(): void
    {
        config(['intermidiate.json' => false]);

        $this->getJson('/desks/news')->assertOk()->assertSee('<h1>News desk</h1>', false);
    }

    public function test_bad_route_values_fail_loudly(): void
    {
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage('expects $desk to be '.Desk::class.', got string "weather"');

        $this->get('/desks/weather');
    }
}
