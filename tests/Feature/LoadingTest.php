<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Fetch;
use Atannex\Intermidiate\Attributes\Paginate;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Models\Writer;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class LoadingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function defineRoutes($router): void
    {
        Route::presenter('/front/{year}', FrontPresenter::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // $articles → App\Models\Article, as in an app.
        if (! class_exists('App\Models\Article')) {
            class_alias(Article::class, 'App\Models\Article');
        }

        Schema::create('writers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id');
            $table->string('title');
            $table->boolean('published')->default(true);
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at');
            $table->timestamps();
        });

        $writer = Writer::create(['name' => 'Ma Ngum']);

        foreach (range(1, 8) as $day) {
            $writer->articles()->create([
                'title' => "Story {$day}",
                'published_at' => "2026-03-0{$day} 08:00:00",
                'published' => $day !== 8,
                'featured' => $day === 5,
            ]);
        }

        $writer->articles()->create(['title' => 'Last year', 'published_at' => '2025-12-31 08:00:00']);
    }

    public function test_a_page_of_models_is_loaded_ordered_scoped_and_narrowed_by_the_other_inputs(): void
    {
        $front = FrontPresenter::make(year: 2026);

        $this->assertInstanceOf(LengthAwarePaginator::class, $front->articles);
        $this->assertSame(7, $front->articles->total(), 'published (scope) and 2026 (articlesQuery)');
        $this->assertSame(['Story 7', 'Story 6', 'Story 5'], $front->articles->getCollection()->pluck('title')->all());
        $this->assertTrue($front->articles->getCollection()->first()->relationLoaded('writer'));

        $this->assertSame(['Last year', 'Story 1'], $front->oldest->pluck('title')->all());
        $this->assertSame('Story 5', $front->featured?->title);
        $this->assertInstanceOf(Paginator::class, $front->simple);
        $this->assertSame('p', $front->simple->getPageName());
        $this->assertInstanceOf(CursorPaginator::class, $front->cursor);
    }

    public function test_the_page_and_query_string_come_from_the_request(): void
    {
        $this->get('/front/2026?page=2&desk=sports')
            ->assertOk()
            ->assertSee('<h1>2026</h1>', false)
            ->assertSee('<li>Story 4 · Ma Ngum</li>', false)
            ->assertSee('desk=sports', false)
            ->assertDontSee('Story 7');

        $this->getJson('/front/2026?page=3')
            ->assertJsonPath('articles.current_page', 3)
            ->assertJsonPath('articles.data.0.title', 'Story 1')
            ->assertJsonPath('featured.title', 'Story 5');
    }

    public function test_data_that_is_passed_wins_and_runs_no_query(): void
    {
        $mine = new LengthAwarePaginator([], 0, 3);
        DB::enableQueryLog();

        $front = FrontPresenter::make(year: 2026, articles: $mine, oldest: new Collection, featured: null, simple: new \Illuminate\Pagination\Paginator([], 2), cursor: new \Illuminate\Pagination\CursorPaginator([], 2));

        $this->assertSame($mine, $front->articles);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_mistakes_are_explained(): void
    {
        try {
            Nameless::make();
            $this->fail('No model.');
        } catch (PresenterException $exception) {
            $this->assertStringContainsString('no model found (tried App\Models\Thing, App\Thing). Name it: #[Fetch(Post::class)]', $exception->getMessage());
        }

        $this->expectExceptionMessage('make it static');

        NotStatic::make();
    }
}

final class FrontPresenter extends Presenter
{
    public function __construct(
        public readonly int $year,
        #[Paginate(perPage: 3, latest: 'published_at', with: 'writer', scopes: 'published')]
        public readonly LengthAwarePaginator $articles,
        #[Fetch(Article::class, limit: 2, oldest: 'published_at')]
        public readonly Collection $oldest,
        #[Fetch(scopes: 'featured')]
        public readonly ?Article $featured,
        #[Paginate(Article::class, perPage: 2, pageName: 'p')]
        public readonly Paginator $simple,
        #[Paginate(Article::class, perPage: 2, oldest: 'id')]
        public readonly CursorPaginator $cursor,
    ) {}

    /** Found by name: narrows $articles with the other inputs. */
    public static function articlesQuery(Builder $query, int $year): Builder
    {
        return $query->whereYear('published_at', (string) $year);
    }
}

final class Nameless extends Presenter
{
    public function __construct(#[Fetch] public readonly Collection $things) {}
}

final class NotStatic extends Presenter
{
    public function __construct(#[Fetch(Article::class)] public readonly Collection $items) {}

    public function itemsQuery(Builder $query): Builder
    {
        return $query;
    }
}
