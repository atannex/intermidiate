<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature\Examples;

use Atannex\Intermidiate\Facades\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Models\CreatesArticles;
use Atannex\Intermidiate\Tests\Fixtures\Models\Writer;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

/**
 * The three complete examples of docs/examples.md. Each is a small Laravel
 * feature written twice: plain Laravel (controllers, Blade components, a
 * mailable) under /before, and with Intermidiate under /after. Both must
 * serve the same HTML; the difference is where the display logic lives.
 */
final class ExamplesTest extends TestCase
{
    use CreatesArticles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createArticles();
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('name');
            $table->integer('price');
            $table->integer('cost');
            $table->integer('stock');
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->string('title');
            $table->integer('minutes');
            $table->boolean('done');
        });

        $writer = Writer::create(['name' => 'Ma Ngum']);
        $writer->articles()->create(['title' => 'Rain in Fontem', 'body' => str_repeat('word ', 450), 'published_at' => '2026-03-01 09:30:00']);
        $writer->articles()->create(['title' => 'Dry season', 'body' => 'Dust.']);
        Article::create(['title' => 'Notes', 'body' => 'Short.']);

        Product::create(['sku' => 'TEA-250', 'name' => 'Highland tea', 'price' => 2450, 'cost' => 900, 'stock' => 0]);
        Product::create(['sku' => 'COF-500', 'name' => 'Arabica', 'price' => 5100, 'cost' => 2000, 'stock' => 12]);

        $course = Course::create(['title' => 'Bamileke history']);
        $course->lessons()->create(['title' => 'Origins', 'minutes' => 25, 'done' => true]);
        $course->lessons()->create(['title' => 'Chiefdoms', 'minutes' => 40, 'done' => false]);
        Course::create(['title' => 'Pidgin basics'])->lessons()->create(['title' => 'Greetings', 'minutes' => 15, 'done' => true]);

        config(['intermidiate.models' => [Article::class => ArticlePresenter::class, Product::class => ProductPresenter::class, Course::class => CoursePresenter::class, Lesson::class => LessonPresenter::class]]);
        $this->app->forgetInstance(\Atannex\Intermidiate\Intermidiate::class);

        Blade::component('before-card', BeforeArticleCard::class);
        Blade::component('before-header', BeforeArticleHeader::class);
        Blade::component('before-product', BeforeProductCard::class);

        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::get('/before/news', [BeforeNewsController::class, 'index']);
            Route::get('/before/news/{article}', [BeforeNewsController::class, 'show']);
            Route::get('/after/news', [AfterNewsController::class, 'index']);
            Route::get('/after/news/{article}', [AfterNewsController::class, 'show']);

            Route::get('/before/shop', [BeforeShopController::class, 'index']);
            Route::get('/before/shop/{product}', [BeforeShopController::class, 'show']);
            Route::get('/after/shop', [AfterShopController::class, 'index']);
            Route::get('/after/shop/{product}', [AfterShopController::class, 'show']);

            Route::get('/before/learn', [BeforeLearningController::class, 'dashboard']);
            Route::get('/before/learn/{course}', [BeforeLearningController::class, 'show']);
            Route::get('/after/learn', [AfterLearningController::class, 'dashboard']);
            Route::get('/after/learn/{course}', [AfterLearningController::class, 'show']);
        });
    }

    public function test_example_1_news_pages_render_the_same_with_the_logic_in_one_class(): void
    {
        foreach (['/news', '/news/1'] as $page) {
            $this->assertSame($this->html('/before'.$page), $this->html('/after'.$page), $page);
        }

        $this->assertStringContainsString('By Ma Ngum · 3 min read', $this->html('/after/news'));
        $this->assertStringContainsString('Staff · 1 min read', $this->html('/after/news'));
        $this->assertStringContainsString('<p>By Ma Ngum, 1 Mar 2026</p>', $this->html('/after/news/1'));
    }

    public function test_example_2_shop_pages_and_the_order_email_show_the_same_price(): void
    {
        foreach (['/shop', '/shop/1', '/shop/2'] as $page) {
            $this->assertSame($this->html('/before'.$page), $this->html('/after'.$page), $page);
        }

        $product = Product::findOrFail(1);
        $this->assertSame((new BeforeOrderPlaced($product))->render(), (new AfterOrderPlaced($product))->render());
        $this->assertStringContainsString('Highland tea for 24.50 XAF', (new AfterOrderPlaced($product))->render());

        // The serialized form (a queued payload, a Livewire property) never carries the cost price.
        $this->assertSame(['sku' => 'TEA-250', 'name' => 'Highland tea', 'priceLabel' => '24.50 XAF', 'availability' => 'out of stock'], Intermidiate::wrap($product)->toArray());
    }

    public function test_example_3_learning_pages_render_the_same_without_controller_mapping(): void
    {
        foreach (['/learn', '/learn/1'] as $page) {
            $this->assertSame($this->html('/before'.$page), $this->html('/after'.$page), $page);
        }

        $this->assertStringContainsString('Bamileke history: 1 of 2 lessons · 65 min', $this->html('/after/learn'));
        $this->assertStringContainsString('<li>Origins (25 min) ✓</li>', $this->html('/after/learn/1'));
    }

    public function test_the_after_pages_run_the_same_queries_as_the_before_pages(): void
    {
        $this->assertNotEmpty($this->queries('/before/news'));

        foreach (['/news', '/news/1', '/shop', '/shop/1', '/learn', '/learn/1'] as $page) {
            $this->assertSame($this->queries('/before'.$page), $this->queries('/after'.$page), $page);
        }
    }

    public function test_a_page_with_nothing_to_derive_needs_no_presenter(): void
    {
        // An account page shows the writer's name as stored: nothing to derive, so
        // no WriterPresenter exists, and the model reaches the view untouched.
        $writer = AccountPage::make(writer: Writer::query()->firstOrFail())->get('writer');

        $this->assertInstanceOf(Writer::class, $writer);
        $this->assertSame('<p>Ma Ngum</p>', Blade::render('<p>{{ $writer->name }}</p>', ['writer' => $writer]));
    }

    private function html(string $uri): string
    {
        return (string) $this->get($uri)->assertOk()->getContent();
    }

    /** @return list<array{string, array<mixed>}> each query's SQL and bindings */
    private function queries(string $uri): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        $queries = array_map(fn (array $query) => [$query['query'], $query['bindings']], DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}

// ---------------------------------------------------------------- models

final class Product extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class Course extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}

final class Lesson extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['done' => 'bool'];
}

// ------------------------------------------------- example 1, plain Laravel

final class BeforeNewsController
{
    public function index(): View
    {
        return view('examples.before.home', ['articles' => Article::with('writer')->orderBy('id')->get()]);
    }

    public function show(Article $article): View
    {
        return view('examples.before.article', ['article' => $article->load('writer.articles')]);
    }
}

final class BeforeArticleCard extends Component
{
    public function __construct(public Article $article) {}

    public function permalink(): string
    {
        return url('/articles/'.$this->article->id);
    }

    public function byline(): string
    {
        return $this->article->writer ? 'By '.$this->article->writer->name : 'Staff';
    }

    public function readingTime(): string
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->article->body)) / 200)).' min read';
    }

    public function render(): string
    {
        return '<article><a href="{{ $permalink() }}">{{ $article->title }}</a> <small>{{ $byline() }} · {{ $readingTime() }}</small></article>';
    }
}

final class BeforeArticleHeader extends Component
{
    public function __construct(public Article $article) {}

    public function byline(): string
    {
        return $this->article->writer ? 'By '.$this->article->writer->name : 'Staff';
    }

    public function published(): string
    {
        return $this->article->published_at?->translatedFormat('j M Y') ?? 'Draft';
    }

    public function render(): string
    {
        return '<header><h1>{{ $article->title }}</h1><p>{{ $byline() }}, {{ $published() }}</p></header>';
    }
}

// ------------------------------------------------ example 1, Intermidiate

final class AfterNewsController
{
    public function index(): View
    {
        return view('examples.after.home', ['articles' => Intermidiate::wrap(Article::with('writer')->orderBy('id')->get())]);
    }

    public function show(Article $article): View
    {
        $article->load('writer.articles');

        return view('examples.after.article', [
            'article' => Intermidiate::wrap($article),
            'more' => Intermidiate::wrap($article->writer->articles),
        ]);
    }
}

/** @extends ModelPresenter<Article> */
final class ArticlePresenter extends ModelPresenter
{
    protected array $visible = ['id', 'title', 'permalink', 'byline'];

    public function permalink(): string
    {
        return url('/articles/'.$this->subject->id);
    }

    public function byline(): string
    {
        return $this->subject->writer ? 'By '.$this->subject->writer->name : 'Staff';
    }

    public function readingTime(): string
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->subject->body)) / 200)).' min read';
    }

    public function published(): string
    {
        return $this->subject->published_at?->translatedFormat('j M Y') ?? 'Draft';
    }
}

// ------------------------------------------------- example 2, plain Laravel

final class BeforeShopController
{
    public function index(): View
    {
        return view('examples.before.catalogue', ['products' => Product::orderBy('id')->get()]);
    }

    public function show(Product $product): View
    {
        return view('examples.before.product', ['product' => $product]);
    }
}

final class BeforeProductCard extends Component
{
    public function __construct(public Product $product) {}

    public function priceLabel(): string
    {
        return number_format($this->product->price / 100, 2).' XAF';
    }

    public function availability(): string
    {
        return $this->product->stock > 0 ? $this->product->stock.' in stock' : 'out of stock';
    }

    public function render(): string
    {
        return '<div>{{ $product->name }}: {{ $priceLabel() }} ({{ $availability() }})</div>';
    }
}

final class BeforeOrderPlaced extends Mailable
{
    public function __construct(public Product $product) {}

    public function content(): Content
    {
        return new Content(view: 'examples.before.order-mail', with: [
            'name' => $this->product->name,
            'price' => number_format($this->product->price / 100, 2).' XAF',
        ]);
    }
}

// ------------------------------------------------ example 2, Intermidiate

final class AfterShopController
{
    public function index(): View
    {
        return view('examples.after.catalogue', ['products' => Intermidiate::wrap(Product::orderBy('id')->get())]);
    }

    public function show(Product $product): View
    {
        return view('examples.after.product', ['product' => Intermidiate::wrap($product)]);
    }
}

final class AfterOrderPlaced extends Mailable
{
    // Private: a mailable's public properties would overwrite the view data.
    public function __construct(private Product $product) {}

    public function content(): Content
    {
        return new Content(view: 'examples.after.order-mail', with: ['product' => Intermidiate::wrap($this->product)]);
    }
}

/** @extends ModelPresenter<Product> */
final class ProductPresenter extends ModelPresenter
{
    protected array $visible = ['sku', 'name', 'priceLabel', 'availability'];

    public function priceLabel(): string
    {
        return number_format($this->subject->price / 100, 2).' XAF';
    }

    public function availability(): string
    {
        return $this->subject->stock > 0 ? $this->subject->stock.' in stock' : 'out of stock';
    }
}

// ------------------------------------------------- example 3, plain Laravel

final class BeforeLearningController
{
    public function dashboard(): View
    {
        $models = Course::with('lessons')->orderBy('id')->get();
        $courses = $models->map(fn (Course $course) => [
            'title' => $course->title,
            'progress' => sprintf('%d of %d lessons · %d min', $course->lessons->where('done', true)->count(), $course->lessons->count(), $course->lessons->sum('minutes')),
        ]);
        $lessons = $models->flatMap->lessons;

        return view('examples.before.dashboard', [
            'courses' => $courses,
            'summary' => sprintf('%d courses · %d of %d lessons done', $models->count(), $lessons->where('done', true)->count(), $lessons->count()),
        ]);
    }

    public function show(Course $course): View
    {
        $course->load('lessons');

        return view('examples.before.course', [
            'course' => $course,
            'progress' => sprintf('%d of %d lessons · %d min', $course->lessons->where('done', true)->count(), $course->lessons->count(), $course->lessons->sum('minutes')),
        ]);
    }
}

// ------------------------------------------------ example 3, Intermidiate

final class AfterLearningController
{
    public function dashboard(): Dashboard
    {
        return Dashboard::make(courses: Course::with('lessons')->orderBy('id')->get());
    }

    public function show(Course $course): View
    {
        return view('examples.after.course', ['course' => Intermidiate::wrap($course->load('lessons'))]);
    }
}

/** A page-level value (the summary) beside the courses, which arrive presented. */
final class Dashboard extends Presenter
{
    /** @param  EloquentCollection<int, Course>  $courses */
    public function __construct(public readonly EloquentCollection $courses) {}

    public function summary(): string
    {
        $lessons = $this->courses->flatMap->lessons;

        return sprintf('%d courses · %d of %d lessons done', $this->courses->count(), $lessons->where('done', true)->count(), $lessons->count());
    }

    public function template(): string
    {
        return 'examples.after.dashboard';
    }
}

/** @extends ModelPresenter<Course> */
final class CoursePresenter extends ModelPresenter
{
    protected array $visible = ['title', 'progress', 'lessons'];

    public function progress(): string
    {
        $lessons = $this->subject->lessons;

        return sprintf('%d of %d lessons · %d min', $lessons->where('done', true)->count(), $lessons->count(), $lessons->sum('minutes'));
    }

    /** @return Collection<int, LessonPresenter> */
    public function lessons(): Collection
    {
        return $this->present($this->subject->lessons);
    }
}

/** @extends ModelPresenter<Lesson> */
final class LessonPresenter extends ModelPresenter
{
    protected array $visible = ['label'];

    public function label(): string
    {
        return "{$this->subject->title} ({$this->subject->minutes} min)".($this->subject->done ? ' ✓' : '');
    }
}

// ---------------------------------------------------------- not worth it

final class AccountPage extends Presenter
{
    public function __construct(public readonly Writer $writer) {}
}
