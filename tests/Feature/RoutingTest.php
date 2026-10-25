<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Query;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Reporter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RoutingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // No controller: the route's parameters and the query string fill the Presenter.
        Route::presenter('/beat/{desk}/{reporter:slug}', BeatPresenter::class)->name('beat');
        Route::presenter('/by-id/{reporter}/{desk?}', BeatPresenter::class);

        // A bound view takes what the controller didn't pass from the route.
        Route::get('/raw/{year}', fn () => view('archive', ['posts' => $this->stories(3)]));
    }

    public function test_a_route_serves_a_presenter_with_no_controller(): void
    {
        $this->get('/beat/sports/ma-ngum?page=3&q=rain&sort=oldest')
            ->assertOk()
            ->assertSee('<h1>Ma Ngum · Sports desk</h1><p>3 rain oldest</p>', false)
            ->assertPresenter(BeatPresenter::class, fn (BeatPresenter $beat) => $beat->reporter->id === 1);

        $this->assertSame('http://localhost/beat/news/kola-ndi', route('beat', ['news', 'kola-ndi']));
    }

    public function test_models_are_found_by_route_key_or_binding_field(): void
    {
        $this->get('/by-id/2')->assertOk()->assertSee('<h1>Kola Ndi · News desk</h1>', false);
        $this->get('/beat/news/nobody')->assertNotFound();
        $this->get('/by-id/9')->assertNotFound();
    }

    public function test_a_route_value_that_does_not_fit_is_a_404(): void
    {
        $this->get('/beat/weather/ma-ngum')->assertNotFound();
    }

    public function test_bad_query_values_fall_back_to_the_defaults(): void
    {
        $this->get('/beat/sports/ma-ngum?page=abc&sort=sideways')
            ->assertOk()
            ->assertSee('<p>1 - latest</p>', false);
    }

    public function test_json_from_a_presenter_route(): void
    {
        $this->getJson('/beat/sports/kola-ndi?page=2')
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('desk', 'sports');
    }

    public function test_a_bound_view_reads_inputs_the_controller_left_out_from_the_route(): void
    {
        $this->get('/raw/2026')
            ->assertOk()
            ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->year === 2026);
    }
}

enum Sort: string
{
    case Latest = 'latest';
    case Oldest = 'oldest';
}

final class BeatPresenter extends Presenter
{
    public function __construct(
        public readonly Reporter $reporter,
        public readonly Desk $desk = Desk::News,
        #[Query] public readonly int $page = 1,
        #[Query('q')] public readonly ?string $search = null,
        #[Query] public readonly Sort $sort = Sort::Latest,
    ) {}
}
