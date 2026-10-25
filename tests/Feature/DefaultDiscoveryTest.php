<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Tests\TestCase;

final class DefaultDiscoveryTest extends TestCase
{
    protected array $intermidiate = ['discover' => null];

    public function test_with_no_discover_config_the_apps_own_presenters_folder_is_scanned(): void
    {
        $this->assertSame(
            [$this->app->getNamespace().'Presenters' => $this->app->path('Presenters')],
            $this->app->make(Intermidiate::class)->namespaces(),
        );
    }
}
