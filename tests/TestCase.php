<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests;

use Atannex\Intermidiate\IntermidiateServiceProvider;
use Atannex\Intermidiate\Support\Schema;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Illuminate\Pagination\LengthAwarePaginator;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> config overrides for the next app */
    protected array $intermidiate = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::flush();
    }

    protected function getPackageProviders($app): array
    {
        return [IntermidiateServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
        $app['config']->set('intermidiate.discover', ['Atannex\\Intermidiate\\Tests\\Fixtures\\Presenters' => __DIR__.'/Fixtures/Presenters']);
        $app['config']->set('intermidiate.cache', sys_get_temp_dir().'/intermidiate-tests-'.getmypid().'.php');

        foreach ($this->intermidiate as $key => $value) {
            $app['config']->set("intermidiate.{$key}", $value);
        }
    }

    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir().'/intermidiate-tests-'.getmypid().'.php');

        parent::tearDown();
    }

    /**
     * Page $page of $total stories, $perPage a page.
     *
     * @return LengthAwarePaginator<int, Story>
     */
    protected function stories(int $total, int $perPage = 10, int $page = 1, string $path = '/archive/2026'): LengthAwarePaginator
    {
        $first = ($page - 1) * $perPage + 1;
        $items = [];

        for ($id = $first; $id <= min($total, $first + $perPage - 1); $id++) {
            $items[] = new Story($id, "Story {$id}", $id % 3 === 0);
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => $path]);
    }
}
