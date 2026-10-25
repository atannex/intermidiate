<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\ViewModels\ArchiveViewModel;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class CommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->app->path('ViewModels'));

        parent::tearDown();
    }

    public function test_cache_and_clear(): void
    {
        $path = $this->app->make(Intermidiate::class)->cachePath();

        $this->artisan('view-model:cache')->expectsOutputToContain('Cached 1 ViewModel binding.')->assertSuccessful();
        $this->assertFileExists($path);
        $this->assertSame([ArchiveViewModel::class => ['archive']], require $path);

        $this->artisan('view-model:clear')->assertSuccessful();
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
        $this->artisan('view-model:list')
            ->expectsOutputToContain(ArchiveViewModel::class)
            ->expectsOutputToContain('$ledger')
            ->expectsOutputToContain('Atannex\Intermidiate\Slicing\Group')
            ->assertSuccessful();

        $this->artisan('view-model:list', ['class' => 'ArchiveViewModel', '--json' => true])
            ->expectsOutputToContain('"related": "Illuminate\\\\Support\\\\Collection (lazy)"')
            ->assertSuccessful();

        $this->artisan('view-model:list', ['class' => 'Nope'])->assertFailed();
    }

    public function test_make_view_model_and_presenter(): void
    {
        $this->artisan('make:view-model', ['name' => 'ArchiveViewModel', '--view' => 'date'])->assertSuccessful();

        $file = $this->app->path('ViewModels/ArchiveViewModel.php');
        $this->assertFileExists($file);
        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace App\ViewModels;', $contents);
        $this->assertStringContainsString("#[ForView('date')]\nfinal class ArchiveViewModel extends ViewModel", $contents);
        $this->assertStringContainsString('use Atannex\Intermidiate\Attributes\ForView;', $contents);

        $this->artisan('make:view-model', ['name' => 'PlainViewModel'])->assertSuccessful();
        $this->assertStringNotContainsString('ForView', (string) file_get_contents($this->app->path('ViewModels/PlainViewModel.php')));

        $this->artisan('make:presenter', ['name' => 'StoryCard'])->assertSuccessful();
        $this->assertStringContainsString('namespace App\ViewModels\Presenters;', (string) file_get_contents($this->app->path('ViewModels/Presenters/StoryCard.php')));
    }
}
