<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Cached;
use Atannex\Intermidiate\Attributes\Expose;
use Atannex\Intermidiate\Attributes\Hidden;
use Atannex\Intermidiate\Attributes\ViewOnly;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Exceptions\UnsafeAccess;
use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Models\CreatesArticles;
use Atannex\Intermidiate\Tests\Fixtures\Models\Writer;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ViewException;

/**
 * Model presenters over real Eloquent models: one display definition, the
 * same in every view, serialized with declared fields only, and never a
 * query of their own.
 */
final class EloquentTest extends TestCase
{
    use CreatesArticles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createArticles();

        $writer = Writer::create(['name' => 'Ma Ngum', 'password' => 'hash']);
        $writer->articles()->create(['title' => 'Rain in Fontem', 'body' => 'Rain closed the road to Dschang for most of the day.', 'published_at' => '2026-03-01 09:30:00']);

        config(['intermidiate.models' => [Article::class => ArticlePresenter::class, Writer::class => WriterPresenter::class]]);
        $this->app->forgetInstance(Intermidiate::class);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    public function test_every_view_shows_a_model_the_same_way(): void
    {
        $presenter = FrontPagePresenter::make(articles: Article::with('writer')->get());
        $articles = $presenter->toArray()['articles'];

        $this->assertInstanceOf(Collection::class, $articles);
        $this->assertInstanceOf(ArticlePresenter::class, $articles->first());
        $this->assertSame($articles, $presenter->get('articles'), 'the view data and get() agree');

        $card = '<a href="{{ $article->permalink }}">{{ $article->title }}</a> {{ $article->byline }}';
        $row = '{{ $article->byline }} · {{ $article->permalink }}';

        $this->assertSame('<a href="/articles/1">Rain in Fontem</a> By Ma Ngum', Blade::render($card, ['article' => $articles->first()]));
        $this->assertSame('By Ma Ngum · /articles/1', Blade::render($row, ['article' => $articles->first()]));
    }

    public function test_computed_fields_run_once_per_presenter(): void
    {
        ArticlePresenter::$bylines = 0;
        $article = ArticlePresenter::make(Article::with('writer')->first());

        $article->byline;
        $article->byline;
        $article->toArray();

        $this->assertSame(1, ArticlePresenter::$bylines);
    }

    public function test_serializing_gives_declared_fields_only(): void
    {
        $article = ArticlePresenter::make(Article::with('writer')->first());

        $this->assertSame([
            'id' => 1,
            'title' => 'Rain in Fontem',
            'writer' => ['name' => 'Ma Ngum', 'initials' => 'MN'],
            'permalink' => '/articles/1',
            'byline' => 'By Ma Ngum',
        ], $article->toArray());
        $this->assertSame($article->toArray(), json_decode((string) json_encode($article), true));
    }

    public function test_an_attribute_the_model_hides_is_refused(): void
    {
        $this->expectException(UnsafeAccess::class);
        $this->expectExceptionMessage('lists "password" in $visible, but '.Writer::class.' hides it');

        LeakyWriterPresenter::make(Writer::first())->toArray();
    }

    public function test_a_related_model_without_a_presenter_is_refused(): void
    {
        config(['intermidiate.models' => [Article::class => ArticlePresenter::class]]);
        $this->app->forgetInstance(Intermidiate::class);

        $this->expectException(UnsafeAccess::class);
        $this->expectExceptionMessage('would serialize a raw '.Writer::class);

        ArticlePresenter::make(Article::with('writer')->first())->toArray();
    }

    public function test_legacy_serialization_serializes_the_whole_model_as_in_1x(): void
    {
        config(['intermidiate.legacy_serialization' => true]);

        $array = ArticlePresenter::make(Article::with('writer')->first())->toArray();

        $this->assertSame('Rain closed the road to Dschang for most of the day.', $array['body']);
        $this->assertSame('By Ma Ngum', $array['byline']);
    }

    public function test_presenter_json_serializes_models_through_their_presenter(): void
    {
        $this->legacyJson(strict: true);

        $json = FrontPagePresenter::make(articles: Article::with('writer')->get())->jsonSerialize();

        $this->assertSame(['id', 'title', 'writer', 'permalink', 'byline'], array_keys($json['articles'][0]));
        $this->assertArrayNotHasKey('body', $json['articles'][0]);
    }

    public function test_strict_presenter_json_refuses_raw_models(): void
    {
        $this->legacyJson(strict: true);
        config(['intermidiate.models' => []]);
        $this->app->forgetInstance(Intermidiate::class);

        $this->expectException(UnsafeAccess::class);
        $this->expectExceptionMessage('would serialize a raw '.Article::class);

        FrontPagePresenter::make(articles: Article::all())->jsonSerialize();
    }

    public function test_legacy_presenter_json_still_serializes_models(): void
    {
        $this->legacyJson();
        config(['intermidiate.models' => []]);
        $this->app->forgetInstance(Intermidiate::class);

        $json = FrontPagePresenter::make(articles: Article::all())->jsonSerialize();

        $this->assertSame('Rain in Fontem', $json['articles'][0]['title']);
        $this->assertArrayHasKey('body', $json['articles'][0], '1.x serialized every visible attribute');
    }

    public function test_presenting_loaded_models_runs_no_queries(): void
    {
        $articles = Article::with('writer')->get();
        DB::enableQueryLog();

        $presenter = FrontPagePresenter::make(articles: $articles);
        Blade::render('@foreach ($articles as $article){{ $article->byline }}{{ $article->permalink }}@endforeach', $presenter->toArray());
        $presenter->articles->first();
        ArticlePresenter::make($articles->first())->toArray();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_reading_an_unloaded_relation_through_a_presenter_fails_before_querying(): void
    {
        $article = ArticlePresenter::make(Article::first());
        DB::enableQueryLog();

        try {
            $article->writer;
            $this->fail('expected UnsafeAccess');
        } catch (UnsafeAccess $e) {
            $this->assertStringContainsString('reads '.Article::class.'::$writer, which is not loaded', $e->getMessage());
        }

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_a_computed_field_using_an_unloaded_relation_fails_before_querying(): void
    {
        $article = ArticlePresenter::make(Article::first());
        DB::enableQueryLog();

        try {
            $article->byline;
            $this->fail('expected LazyLoadingViolationException');
        } catch (LazyLoadingViolationException $e) {
            $this->assertStringContainsString('[writer]', $e->getMessage());
        }

        $this->assertSame([], DB::getQueryLog());
        $this->assertFalse(Article::first()->preventsLazyLoading, 'nothing global changed');
    }

    public function test_relations_of_loaded_relations_are_guarded_too(): void
    {
        $article = Article::with('writer')->first();

        $this->expectException(LazyLoadingViolationException::class);

        DeepPresenter::make($article)->siblings;
    }

    public function test_the_guard_is_lifted_afterwards(): void
    {
        $article = Article::with('writer')->first();

        $this->assertSame('By Ma Ngum', ArticlePresenter::make($article)->byline);
        $this->assertFalse($article->preventsLazyLoading);
        $this->assertFalse($article->writer->preventsLazyLoading);
        $this->assertCount(1, $article->writer->articles, 'outside a presenter, lazy loading is the app\'s business');
    }

    public function test_turning_strict_off_never_turns_on_whole_model_serialization(): void
    {
        config(['intermidiate.strict' => false]);
        $this->assertSame(['id' => 1, 'title' => 'Rain in Fontem', 'writer' => ['name' => 'Ma Ngum', 'initials' => 'MN'], 'permalink' => '/articles/1', 'byline' => 'By Ma Ngum'], ArticlePresenter::make(Article::first())->toArray(), 'lazy loads the writer, but serializes only $visible');

        foreach (['true', '1', 1, 'yes'] as $loose) {
            config(['intermidiate.legacy_serialization' => $loose]);
            $this->assertArrayNotHasKey('body', ArticlePresenter::make(Article::with('writer')->first())->toArray(), var_export($loose, true).' is not true');
        }
    }

    public function test_without_strict_presenters_lazy_load_as_in_1x(): void
    {
        config(['intermidiate.strict' => false]);
        DB::enableQueryLog();

        $this->assertSame('By Ma Ngum', ArticlePresenter::make(Article::first())->byline);
        $this->assertCount(2, DB::getQueryLog(), 'the article, then its writer');
    }

    public function test_an_apps_own_lazy_loading_handler_is_respected(): void
    {
        $violations = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$violations) {
            $violations[] = $relation;
        });

        try {
            // Laravel calls the handler and then loads: detection, by the app's choice.
            $this->assertSame('By Ma Ngum', ArticlePresenter::make(Article::first())->byline);
            $this->assertSame(['writer'], $violations);
        } finally {
            Model::handleLazyLoadingViolationUsing(null);
        }
    }

    public function test_newly_created_models_are_not_guarded_inside_computed_fields(): void
    {
        // Laravel never raises lazy-loading violations for models created in this request.
        $article = Writer::first()->articles()->create(['title' => 'Fresh', 'body' => 'b']);
        $this->assertTrue($article->wasRecentlyCreated);
        DB::enableQueryLog();

        $this->assertSame('By Ma Ngum', ArticlePresenter::make($article)->byline, 'the writer is lazy loaded: detection only, not prevention');
        $this->assertCount(1, DB::getQueryLog());

        $this->expectException(UnsafeAccess::class);
        ArticlePresenter::make($article->fresh())->writer;   // reads through the presenter are still refused
    }

    public function test_the_guard_is_lifted_when_a_computed_field_throws(): void
    {
        $article = Article::with('writer')->first();

        try {
            ThrowingPresenter::make($article)->boom;
        } catch (\RuntimeException) {
        }

        $this->assertFalse($article->preventsLazyLoading);
        $this->assertFalse($article->writer->preventsLazyLoading);
        $this->assertCount(1, $article->writer->articles, 'later code (another request in a worker) lazy loads as the app decides');
    }

    public function test_nested_presenters_restore_the_outer_guard(): void
    {
        $article = Article::with('writer')->first();
        $outer = NestingPresenter::make($article);

        $this->assertSame('MN', $outer->writerInitials);
        $this->assertFalse($article->preventsLazyLoading);
        $this->assertFalse($article->writer->preventsLazyLoading);
    }

    public function test_an_edit_gives_a_cached_member_a_new_key(): void
    {
        CachedTitlePresenter::$runs = 0;
        $article = Article::first();

        CachedTitlePresenter::make($article)->loud;
        CachedTitlePresenter::make(Article::first())->loud;
        $this->travel(1)->minutes();
        $article->update(['title' => 'Rain in Buea']);

        $this->assertSame('RAIN IN BUEA', CachedTitlePresenter::make(Article::first())->loud);
        $this->assertSame(2, CachedTitlePresenter::$runs);
    }

    public function test_computed_fields_serialize_only_when_listed(): void
    {
        $listed = ArticlePresenter::make(Article::with('writer')->first())->toArray();
        $unlisted = RenamedArticlePresenter::make(Article::first())->toArray();

        $this->assertArrayHasKey('permalink', $listed);
        $this->assertSame([], $unlisted, 'url() is public for views, but nothing lists it');
    }

    public function test_listing_a_view_only_or_parameterised_method_is_refused(): void
    {
        foreach ([ViewOnlyListed::class => 'is #[ViewOnly]', CallableListed::class => 'takes parameters'] as $class => $message) {
            try {
                $class::make(Article::first())->toArray();
                $this->fail($class);
            } catch (PresenterException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_with_values_and_computed_results_stay_raw_unless_wrapped_explicitly(): void
    {
        $presenter = FrontPagePresenter::make(articles: Article::with('writer')->get())->with('extra', Article::first());

        $this->assertInstanceOf(Article::class, $presenter->toArray()['extra']);
        $this->assertInstanceOf(ArticlePresenter::class, \Atannex\Intermidiate\Facades\Intermidiate::wrap(Article::first()));
        $this->assertContainsOnlyInstancesOf(ArticlePresenter::class, \Atannex\Intermidiate\Facades\Intermidiate::wrap(['a' => Article::first()]));

        $writer = WriterWithArticles::make(Writer::with('articles')->first());
        $this->assertInstanceOf(Article::class, $writer->latest, 'a computed result is the method\'s own value');
        $this->assertContainsOnlyInstancesOf(ArticlePresenter::class, $writer->presented);
    }

    public function test_an_unloaded_relation_listed_in_visible_fails(): void
    {
        $this->expectException(UnsafeAccess::class);
        $this->expectExceptionMessage('$writer, which is not loaded');

        VisibleWriterPresenter::make(Article::first())->toArray();
    }

    public function test_how_export_attributes_shape_the_view_and_serialized_output(): void
    {
        $presenter = ExportsPresenter::make(Article::with('writer')->first());

        $this->assertSame('internal', $presenter->note, '#[ViewOnly]: in the view');
        $this->assertSame('Rain in Fontem', $presenter->headline, '#[Expose]: under its new name');
        $this->assertSame('RAIN IN FONTEM', $presenter->title, 'a computed field shadows the attribute of the same name');
        $this->assertSame(['title' => 'RAIN IN FONTEM', 'headline' => 'Rain in Fontem'], ExportsPresenter::make(Article::first())->toArray(), '#[ViewOnly] and #[Hidden] stay out; the computed title wins over $visible');
    }

    public function test_computed_fields_cannot_smuggle_out_raw_models_or_objects(): void
    {
        foreach (['model', 'models', 'nested', 'object'] as $field) {
            try {
                SmugglerPresenter::make(Article::with('writer')->first(), $field)->toArray();
                $this->fail("{$field} was serialized");
            } catch (UnsafeAccess $e) {
                $this->assertMatchesRegularExpression('/raw|can\'t serialize/', $e->getMessage());
            }
        }
    }

    public function test_a_renamed_member_fails_the_views_that_read_it_with_laravels_strict_mode(): void
    {
        // Why there is no output manifest: a member renamed away (permalink → url)
        // falls through to the model, and Laravel's strict mode already fails every
        // view test that still reads it, with the member's name.
        Model::preventAccessingMissingAttributes();

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('The attribute [permalink] either does not exist');

        Blade::render('{{ $article->permalink }}', ['article' => RenamedArticlePresenter::make(Article::first())]);
    }

    public function test_a_circular_graph_fails_instead_of_recursing(): void
    {
        $writer = Writer::with('articles')->first();

        $this->expectException(UnsafeAccess::class);
        $this->expectExceptionMessage('circular');

        CircularWriterPresenter::make($writer)->toArray();
    }

    public function test_an_eloquent_collection_without_presenters_stays_one(): void
    {
        config(['intermidiate.models' => []]);
        $this->app->forgetInstance(Intermidiate::class);

        $this->assertInstanceOf(EloquentCollection::class, FrontPagePresenter::make(articles: Article::all())->get('articles'));
    }
}

final class FrontPagePresenter extends Presenter
{
    /** @param  Collection<int, Article>  $articles */
    public function __construct(public readonly Collection $articles) {}
}

/** @extends ModelPresenter<Article> */
final class ArticlePresenter extends ModelPresenter
{
    public static int $bylines = 0;

    protected array $visible = ['id', 'title', 'writer', 'permalink', 'byline'];

    public function permalink(): string
    {
        return '/articles/'.$this->subject->id;
    }

    public function byline(): string
    {
        self::$bylines++;

        return 'By '.$this->subject->writer->name;
    }
}

/** @extends ModelPresenter<Article> */
final class ThrowingPresenter extends ModelPresenter
{
    public function boom(): never
    {
        throw new \RuntimeException('boom');
    }
}

/** @extends ModelPresenter<Article> */
final class NestingPresenter extends ModelPresenter
{
    public function writerInitials(): string
    {
        return WriterPresenter::make($this->subject->writer)->initials;
    }
}

/** @extends ModelPresenter<Article> */
final class CachedTitlePresenter extends ModelPresenter
{
    public static int $runs = 0;

    #[Cached(minutes: 60, shared: true)]
    public function loud(): string
    {
        self::$runs++;

        return mb_strtoupper($this->subject->title);
    }
}

/** @extends ModelPresenter<Article> */
final class ViewOnlyListed extends ModelPresenter
{
    protected array $visible = ['note'];

    #[ViewOnly]
    public function note(): string
    {
        return 'n';
    }
}

/** @extends ModelPresenter<Article> */
final class CallableListed extends ModelPresenter
{
    protected array $visible = ['link'];

    public function link(string $base): string
    {
        return $base;
    }
}

/** @extends ModelPresenter<Writer> */
final class WriterWithArticles extends ModelPresenter
{
    public function latest(): ?Article
    {
        return $this->subject->articles->first();
    }

    /** @return Collection<int, ArticlePresenter> */
    public function presented(): Collection
    {
        return $this->present($this->subject->articles);
    }
}

/** @extends ModelPresenter<Article> */
final class DeepPresenter extends ModelPresenter
{
    public function siblings(): int
    {
        return $this->subject->writer->articles->count();
    }
}

/** @extends ModelPresenter<Article> */
final class VisibleWriterPresenter extends ModelPresenter
{
    protected array $visible = ['title', 'writer'];
}

/** @extends ModelPresenter<Article> */
final class ExportsPresenter extends ModelPresenter
{
    protected array $visible = ['title', 'headline'];

    public function title(): string
    {
        return mb_strtoupper($this->subject->title);
    }

    #[Expose('headline')]
    public function original(): string
    {
        return $this->subject->title;
    }

    #[ViewOnly]
    public function note(): string
    {
        return 'internal';
    }

    #[Hidden]
    public function secret(): string
    {
        return 'never a field';
    }
}

/** @extends ModelPresenter<array{0: Article, 1: string}> */
final class SmugglerPresenter extends ModelPresenter
{
    protected array $visible = ['leak'];

    public static function make(mixed $subject, string $field = 'model'): static
    {
        return new self([$subject, $field]);
    }

    public function leak(): mixed
    {
        [$article, $field] = $this->subject;

        return match ($field) {
            'model' => $article->writer,
            'models' => collect([$article->writer]),
            'nested' => ['by' => ['writer' => $article->writer]],
            default => new SmuggledValue,
        };
    }
}

final class SmuggledValue
{
    public string $password = 'hash';
}

/** @extends ModelPresenter<Article> */
final class RenamedArticlePresenter extends ModelPresenter
{
    public function url(): string
    {
        return '/articles/'.$this->subject->id;
    }
}

/** @extends ModelPresenter<Writer> */
final class WriterPresenter extends ModelPresenter
{
    protected array $visible = ['name', 'initials'];

    public function initials(): string
    {
        return implode('', array_map(fn (string $word) => $word[0], explode(' ', $this->subject->name)));
    }
}

/** @extends ModelPresenter<Writer> */
final class LeakyWriterPresenter extends ModelPresenter
{
    protected array $visible = ['name', 'password'];
}

/** @extends ModelPresenter<Writer> */
final class CircularWriterPresenter extends ModelPresenter
{
    protected array $visible = ['name', 'stories'];

    /** @return list<array<string, mixed>> */
    public function stories(): array
    {
        return $this->subject->articles->map(fn (Article $article) => ['title' => $article->title, 'by' => $this])->all();
    }
}
