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

        // The Presenter is the route's action itself, the way an attribute router registers it.
        Route::get('/desk/{desk}', DeskPagePresenter::class);
        Route::get('/staff/{reporter}', StaffPagePresenter::class);

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

    public function test_a_presenter_can_be_the_route_action_with_its_data_from_source(): void
    {
        $this->get('/desk/sports')
            ->assertOk()
            ->assertSee('<h1>Sports desk</h1><p>4 stories · SPORTS</p>', false)
            ->assertPresenter(DeskPagePresenter::class, fn (DeskPagePresenter $page) => $page->count === 4);

        $this->get('/desk/weather')->assertNotFound();
        $this->getJson('/desk/news')->assertOk()->assertJsonPath('count', 4)->assertJsonPath('heading', 'NEWS');
    }

    public function test_source_looks_up_models_from_the_route(): void
    {
        $this->get('/staff/2')->assertOk()->assertSee('<h1>Kola Ndi</h1>', false);
        $this->get('/staff/9')->assertNotFound();
    }

    public function test_a_bound_view_reads_inputs_the_controller_left_out_from_the_route(): void
    {
        $this->get('/raw/2026')
            ->assertOk()
            ->assertPresenter(ArchivePresenter::class, fn (ArchivePresenter $presenter) => $presenter->year === 2026);
    }
}

/** Serves tests/Fixtures/views/desk-page.blade.php from its own route, loading through source(). */
final class DeskPagePresenter extends Presenter
{
    public function __construct(
        public readonly string $title,
        public readonly int $count,
        private readonly string $slug,
    ) {}

    /** @return array<string, mixed>|null */
    public static function source(string $desk): ?array
    {
        return in_array($desk, ['news', 'sports'], true)
            ? ['title' => ucfirst($desk).' desk', 'count' => 4, 'slug' => $desk, 'raw' => 'passed through']
            : null;
    }

    public function heading(): string
    {
        return strtoupper($this->slug);
    }

    public function template(): ?string
    {
        return 'desk-page';
    }
}

final class StaffPagePresenter extends Presenter
{
    public function __construct(public readonly string $name) {}

    /** @return array<string, mixed> */
    public static function source(Reporter $reporter): array
    {
        return ['name' => $reporter->name];
    }

    public function template(): ?string
    {
        return 'staff-page';
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
