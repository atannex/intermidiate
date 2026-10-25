<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class CommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->app->path('Presenters'));

        parent::tearDown();
    }

    public function test_cache_and_clear(): void
    {
        $path = $this->app->make(Intermidiate::class)->cachePath();

        $this->artisan('presenter:cache')->expectsOutputToContain('Cached 1 Presenter binding.')->assertSuccessful();
        $this->assertFileExists($path);
        $this->assertSame([ArchivePresenter::class => ['archive']], require $path);

        $this->artisan('presenter:clear')->assertSuccessful();
        $this->assertFileDoesNotExist($path);
    }

    public function test_the_cache_is_read_instead_of_scanning(): void
    {
        $path = $this->app->make(Intermidiate::class)->cachePath();
        file_put_contents($path, '<?php return [];');

        $this->assertSame([], $this->app->make(Intermidiate::class)->discovered());
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

    public function test_make_view_model_and_presenter(): void
    {
        $this->artisan('make:presenter', ['name' => 'ArchivePresenter', '--view' => 'date'])->assertSuccessful();

        $file = $this->app->path('Presenters/ArchivePresenter.php');
        $this->assertFileExists($file);
        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\Presenters;', $contents);
        $this->assertStringContainsString("#[ForView('date')]\nfinal class ArchivePresenter extends Presenter", $contents);
        $this->assertStringContainsString('use Atannex\Intermidiate\Attributes\ForView;', $contents);

        $this->artisan('make:presenter', ['name' => 'PlainPresenter'])->assertSuccessful();
        $this->assertStringNotContainsString('ForView', (string) file_get_contents($this->app->path('Presenters/PlainPresenter.php')));

        $this->artisan('make:model-presenter', ['name' => 'StoryCard'])->assertSuccessful();
        $this->assertStringContainsString('namespace App\Presenters\Models;', (string) file_get_contents($this->app->path('Presenters/Models/StoryCard.php')));
    }
}
