<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Raw;
use Atannex\Intermidiate\Facades\Intermidiate as IntermidiateFacade;
use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Slicing\Partition;
use Atannex\Intermidiate\Slicing\Partitioned;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Models\CreatesArticles;
use Atannex\Intermidiate\Tests\Fixtures\Models\Writer;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * Exactly where a page Presenter's models arrive wrapped in their model
 * presenter, and where they stay raw.
 */
final class WrappingTest extends TestCase
{
    use CreatesArticles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createArticles();
        $writer = Writer::create(['name' => 'Ma Ngum']);

        foreach (['Rain', 'Sun', 'Wind', 'Fog'] as $title) {
            $writer->articles()->create(['title' => $title, 'body' => 'b', 'featured' => $title === 'Rain']);
        }

        config(['intermidiate.models' => [Article::class => LabelPresenter::class]]);
        $this->app->forgetInstance(Intermidiate::class);
    }

    public function test_one_model(): void
    {
        $lead = ShapesPresenter::make(value: Article::first())->get('value');

        $this->assertInstanceOf(LabelPresenter::class, $lead);
        $this->assertSame('[Rain]', $lead->label);
        $this->assertSame(1, $lead->getRouteKey(), 'route() still gets the model\'s key');
    }

    public function test_eloquent_and_base_collections(): void
    {
        foreach ([Article::all(), collect(Article::all()->all())] as $collection) {
            $wrapped = ShapesPresenter::make(value: $collection)->get('value');

            $this->assertInstanceOf(Collection::class, $wrapped);
            $this->assertNotInstanceOf(EloquentCollection::class, $wrapped, 'its items are no longer models');
            $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $wrapped);
            $this->assertSame(['[Rain]', '[Sun]', '[Wind]', '[Fog]'], $wrapped->map->label->all());
        }
    }

    public function test_paginators_keep_their_links_and_counts(): void
    {
        $paginators = [Article::paginate(2), Article::simplePaginate(2), Article::orderBy('id')->cursorPaginate(2)];

        foreach ($paginators as $paginator) {
            $wrapped = ShapesPresenter::make(value: $paginator)->get('value');

            $this->assertInstanceOf($paginator::class, $wrapped);
            $this->assertNotSame($paginator, $wrapped, 'a copy: the Presenter\'s own input keeps its models');
            $this->assertInstanceOf(Article::class, $paginator->items()[0]);
            $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $wrapped->items());
            $this->assertSame($paginator->perPage(), $wrapped->perPage());
            $this->assertSame((string) $paginator->nextPageUrl(), (string) $wrapped->nextPageUrl());

            if ($wrapped instanceof LengthAwarePaginator) {
                $this->assertSame(4, $wrapped->total());
                $this->assertStringContainsString('page=2', (string) $wrapped->links());
            }
        }

        $this->assertInstanceOf(Paginator::class, $paginators[1]);
        $this->assertInstanceOf(CursorPaginator::class, $paginators[2]);
    }

    public function test_nested_collections_are_wrapped_but_arrays_are_left_alone(): void
    {
        $groups = ShapesPresenter::make(value: Article::all()->groupBy('featured'))->get('value');

        $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $groups[1]);
        $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $groups[0]);

        // An array is a container the app built, often to hand to another presenter typed with the model.
        $card = ShapesPresenter::make(value: ['featured' => Article::first()])->get('value');
        $this->assertInstanceOf(Article::class, $card['featured']);
    }

    public function test_groups_and_partitions_keep_their_numbering(): void
    {
        $partitioned = Partition::of(Article::all())->take('lead', 1)->rest('ledger')->get();
        $wrapped = ShapesPresenter::make(value: $partitioned)->get('value');

        $this->assertInstanceOf(Partitioned::class, $wrapped);
        $this->assertInstanceOf(LabelPresenter::class, $wrapped->lead->first());
        $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $wrapped->ledger->items());
        $this->assertSame($partitioned->ledger->start(), $wrapped->ledger->start());

        $group = ShapesPresenter::make(value: $partitioned->ledger)->get('value');
        $this->assertInstanceOf(Group::class, $group);
        $this->assertContainsOnlyInstancesOf(LabelPresenter::class, $group->items());
    }

    public function test_the_bound_view_and_presenter_directive_get_wrapped_models(): void
    {
        IntermidiateFacade::bind('wrapped', FrontViewPresenter::class);

        $this->assertSame('[Rain]|[Rain],[Sun],[Wind],[Fog],', trim(view('wrapped', ['articles' => Article::all()])->render()));
        $this->assertSame('[Rain]', Blade::render('@presenter(\\'.FrontViewPresenter::class.'::class, ["articles" => $articles]){{ $lead->label }}', ['articles' => Article::all()]));
    }

    public function test_what_stays_raw(): void
    {
        $presenter = ShapesPresenter::make(value: Article::all());

        $this->assertInstanceOf(EloquentCollection::class, $presenter->value, 'public properties read directly from PHP');
        $this->assertInstanceOf(Article::class, $presenter->get('raw'), '#[Raw]');
        $this->assertInstanceOf(Writer::class, $presenter->get('writer'), 'models without a presenter');
        $this->assertSame(['a' => 1], ShapesPresenter::make(value: ['a' => 1])->get('value'));

        $this->assertInstanceOf(Writer::class, LabelPresenter::make(Article::with('writer')->first())->writer, 'reads through a model presenter are the model\'s own');
    }

    public function test_a_presented_model_still_routes_calls_and_relates_like_the_model(): void
    {
        Route::get('/a/{article}', fn () => '')->name('a.show');
        Route::get('/t/{article:title}', fn () => '')->name('a.titled');
        $article = Article::with('writer')->first();
        $presented = LabelPresenter::make($article);

        $this->assertSame(route('a.show', $article), route('a.show', $presented));
        $this->assertSame('http://localhost/t/Rain', route('a.titled', $presented), 'a custom binding field is read through');
        $this->assertSame(1, $presented->getKey());
        $this->assertTrue($article->is($presented->unwrap()));
        $this->assertTrue($presented->is(Article::first()), 'model methods are forwarded');
        $this->assertInstanceOf(BelongsTo::class, $presented->writer(), 'relation methods too (an explicit query)');
        $this->assertSame('Ma Ngum', $presented->writer->name);
        $this->assertNotInstanceOf(Article::class, $presented, 'it is not the model: unwrap() or #[Raw] where one is required');
    }

    public function test_resolving_a_route_binding_through_a_presenter_returns_a_presenter(): void
    {
        $resolved = LabelPresenter::make(new Article)->resolveRouteBinding(2);

        $this->assertInstanceOf(LabelPresenter::class, $resolved);
        $this->assertSame('[Sun]', $resolved->label);
    }

    public function test_a_model_presenter_passed_to_a_presenter_typed_with_the_model_is_unwrapped(): void
    {
        $this->assertInstanceOf(Article::class, NeedsArticle::from(['article' => LabelPresenter::make(Article::first())])->article);
    }
}

final class ShapesPresenter extends Presenter
{
    public function __construct(public readonly mixed $value) {}

    #[Raw]
    public function raw(): Article
    {
        return Article::query()->firstOrFail();
    }

    public function writer(): Writer
    {
        return Writer::query()->firstOrFail();
    }
}

final class FrontViewPresenter extends Presenter
{
    /** @param  EloquentCollection<int, Article>  $articles */
    public function __construct(public readonly EloquentCollection $articles) {}

    public function lead(): ?Article
    {
        return $this->articles->first();
    }
}

final class NeedsArticle extends Presenter
{
    public function __construct(public readonly Article $article) {}
}

/** @extends ModelPresenter<Article> */
final class LabelPresenter extends ModelPresenter
{
    public function label(): string
    {
        return '['.$this->subject->title.']';
    }
}
