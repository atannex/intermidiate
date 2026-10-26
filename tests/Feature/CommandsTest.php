<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\BylinePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Editor;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\DeskPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\Models\EditorCard;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class CommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->app->path('Presenters'));

        foreach (['date', 'plain', 'archive/month'] as $view) {
            @unlink(__DIR__.'/../Fixtures/views/'.$view.'.blade.php');
        }

        @rmdir(__DIR__.'/../Fixtures/views/archive');

        parent::tearDown();
    }

    public function test_cache_and_clear(): void
    {
        $path = $this->app->make(Intermidiate::class)->cachePath();

        $this->artisan('presenter:cache')->expectsOutputToContain('Cached 3 Presenter bindings.')->assertSuccessful();
        $this->assertFileExists($path);
        $this->assertSame([
            'attributes' => [ArchivePresenter::class => ['archive']],
            'conventions' => [BylinePresenter::class => ['byline'], DeskPresenter::class => ['desk']],
            'models' => [Editor::class => EditorCard::class],
        ], require $path);

        $this->artisan('presenter:clear')->assertSuccessful();
        $this->assertFileDoesNotExist($path);
    }

    public function test_the_cache_is_read_instead_of_scanning(): void
    {
        $path = $this->app->make(Intermidiate::class)->cachePath();
        file_put_contents($path, '<?php return [];');

        $this->assertSame([], $this->app->make(Intermidiate::class)->discovered());

        // A cache from before conventions holds only the attribute map. What
        // discovery found is kept per instance, so read it with a fresh one.
        file_put_contents($path, '<?php return '.var_export([ArchivePresenter::class => ['archive']], true).';');
        $this->app->forgetInstance(Intermidiate::class);

        $this->assertSame([ArchivePresenter::class => ['archive']], $this->app->make(Intermidiate::class)->discovered());
    }

    public function test_discovery_scans_once_per_instance(): void
    {
        $intermidiate = $this->app->make(Intermidiate::class);
        $found = $intermidiate->discovered();
        $path = $intermidiate->cachePath();

        // A cache written later is not read by an instance that has scanned…
        file_put_contents($path, '<?php return [];');
        $this->assertSame($found, $intermidiate->discovered());

        // …until it is told the cache changed.
        $intermidiate->clearCache();
        $this->assertSame($found, $intermidiate->discovered());
        $this->assertFileDoesNotExist($path);
    }

    public function test_list_shows_views_and_variables(): void
    {
        $this->artisan('presenter:list')
            ->expectsOutputToContain(ArchivePresenter::class)
            ->expectsOutputToContain('$ledger')
            ->expectsOutputToContain('Atannex\Intermidiate\Slicing\Group')
            ->assertSuccessful();

        $this->artisan('presenter:list', ['class' => 'ArchivePresenter', '--json' => true])
            ->expectsOutputToContain('"related": "Illuminate\\\\Support\\\\Collection (lazy)"')
            ->assertSuccessful();

        $this->artisan('presenter:list', ['class' => 'Nope'])->assertFailed();
    }

    public function test_make_presenter_and_model_presenter(): void
    {
        $this->artisan('make:presenter', ['name' => 'ArchivePresenter', '--view' => 'date'])->assertSuccessful();

        $file = $this->app->path('Presenters/ArchivePresenter.php');
        $this->assertFileExists($file);
        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Presenters;', $contents);
        $this->assertStringContainsString("#[ForView('date')]\nfinal class ArchivePresenter extends Presenter", $contents);
        $this->assertStringContainsString('use Atannex\Intermidiate\Attributes\ForView;', $contents);

        $this->assertFileExists(__DIR__.'/../Fixtures/views/date.blade.php');

        $this->artisan('make:presenter', ['name' => 'PlainPresenter'])->assertSuccessful();
        $this->assertStringNotContainsString('ForView', (string) file_get_contents($this->app->path('Presenters/PlainPresenter.php')));
        $this->assertFileExists(__DIR__.'/../Fixtures/views/plain.blade.php');

        // A view name is enough: no suffix, no attribute, and the Blade file comes with it.
        $this->artisan('make:presenter', ['name' => 'archive.month'])->assertSuccessful();
        $contents = (string) file_get_contents($this->app->path('Presenters/Archive/MonthPresenter.php'));
        $this->assertStringContainsString("namespace App\\Presenters\\Archive;", $contents);
        $this->assertStringContainsString('final class MonthPresenter extends Presenter', $contents);
        $this->assertStringNotContainsString('ForView', $contents);
        $this->assertStringContainsString('App\\Presenters\\Archive\\MonthPresenter', (string) file_get_contents(__DIR__.'/../Fixtures/views/archive/month.blade.php'));

        // --view naming the convention's own view needs no attribute either.
        $this->artisan('make:presenter', ['name' => 'Archive', '--view' => 'archive', '--force' => true])->assertSuccessful();
        $this->assertStringNotContainsString('ForView', (string) file_get_contents($this->app->path('Presenters/ArchivePresenter.php')));

        $this->artisan('make:presenter', ['name' => 'Sidebar', '--without-view' => true])->assertSuccessful();
        $this->assertFileDoesNotExist(__DIR__.'/../Fixtures/views/sidebar.blade.php');

        $this->artisan('make:model-presenter', ['name' => 'StoryCard'])->assertSuccessful();
        $this->assertStringContainsString('namespace App\Presenters\Models;', (string) file_get_contents($this->app->path('Presenters/Models/StoryCard.php')));
    }

    public function test_make_model_presenter_follows_the_model(): void
    {
        if (! class_exists('App\\Models\\Post')) {
            @mkdir($this->app->path('Models'), 0755, true);
            file_put_contents($this->app->path('Models/Post.php'), "<?php\n\nnamespace App\\Models;\n\nclass Post {}\n");
            require_once $this->app->path('Models/Post.php');
        }

        // Named after the model: the suffix is added and nothing else is needed.
        $this->artisan('make:model-presenter', ['name' => 'Post'])->assertSuccessful();
        $contents = (string) file_get_contents($this->app->path('Presenters/Models/PostPresenter.php'));
        $this->assertStringContainsString('final class PostPresenter extends ModelPresenter', $contents);
        $this->assertStringContainsString('@extends ModelPresenter<\\App\\Models\\Post>', $contents);
        $this->assertStringNotContainsString('Presents', $contents);

        // Any other name gets #[Presents].
        $this->artisan('make:model-presenter', ['name' => 'StoryRow', '--model' => 'Post'])->assertSuccessful();
        $contents = (string) file_get_contents($this->app->path('Presenters/Models/StoryRow.php'));
        $this->assertStringContainsString("#[Presents(\\App\\Models\\Post::class)]\nfinal class StoryRow", $contents);
        $this->assertStringContainsString('use Atannex\\Intermidiate\\Attributes\\Presents;', $contents);

        @unlink($this->app->path('Models/Post.php'));
    }
}
