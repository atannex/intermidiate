<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\View;

final class TidyTest extends TestCase
{
    private const VIEW = __DIR__.'/../Fixtures/views/tidy/sample.blade.php';

    private const CLASS_FILE = __DIR__.'/../Fixtures/Extra/Tidy/SamplePresenter.php';

    /** @var array<string, string> */
    private array $originals = [];

    protected array $intermidiate = [
        // Only this test's own fixtures, so --fix can't touch the shared ones.
        'discover' => ['Atannex\\Intermidiate\\Tests\\Fixtures\\Extra' => __DIR__.'/../Fixtures/Extra'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([self::VIEW, self::CLASS_FILE] as $file) {
            $this->originals[$file] = (string) file_get_contents($file);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $file => $contents) {
            file_put_contents($file, $contents);
        }

        parent::tearDown();
    }

    public function test_a_bare_presenter_directive_finds_the_views_own_presenter(): void
    {
        $this->assertSame('<span>NEW</span>', trim(View::make('components.badge')->render()));
    }

    public function test_tidy_lists_then_rewrites_wiring_the_conventions_cover(): void
    {
        $this->artisan('presenter:tidy')
            ->expectsOutputToContain('tidy.sample')
            ->expectsOutputToContain('1 place the conventions already cover')
            ->assertSuccessful();

        $this->assertSame($this->originals[self::VIEW], file_get_contents(self::VIEW), 'listing changes nothing');

        $this->artisan('presenter:tidy', ['--fix' => true, '--attributes' => true])
            ->expectsOutputToContain('Tidied 2 places')
            ->assertSuccessful();

        $view = (string) file_get_contents(self::VIEW);
        $this->assertStringStartsWith("@presenter\n<p>", $view);
        $this->assertStringContainsString('BylinePresenter::class, [', $view, 'another class, or extra data: left alone');

        $class = (string) file_get_contents(self::CLASS_FILE);
        $this->assertStringNotContainsString("#[ForView('", $class);
        $this->assertStringNotContainsString('use Atannex\\Intermidiate\\Attributes\\ForView;', $class);
        $this->assertStringContainsString("use Atannex\\Intermidiate\\Presenter;\n\n/** Bound by", $class);
    }
}
