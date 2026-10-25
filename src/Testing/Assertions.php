<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Testing;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\ViewModel;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\View;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * @internal Registers TestResponse::assertViewModel().
 *
 *     $this->get('/archive/2026')->assertViewModel(ArchiveViewModel::class, fn ($vm) => $vm->lead !== null);
 */
final class Assertions
{
    public static function register(): void
    {
        if (! class_exists(TestResponse::class) || TestResponse::hasMacro('assertViewModel')) {
            return;
        }

        TestResponse::macro('assertViewModel', function (string $class, ?Closure $callback = null) {
            /** @var TestResponse $this */
            $found = Assertions::find($this, $class);

            Assert::assertNotNull($found, "The response wasn't built with a {$class}.");

            if ($callback !== null) {
                Assert::assertNotFalse($callback($found), "The {$class} didn't pass the given test.");
            }

            return $this;
        });
    }

    /**
     * @param  class-string<ViewModel>  $class
     */
    public static function find(TestResponse $response, string $class): ?ViewModel
    {
        $original = $response->baseResponse->original ?? null;

        if ($original instanceof View) {
            foreach ($original->getData() as $value) {
                if ($value instanceof $class) {
                    return $value;
                }
            }
        }

        $container = Container::getInstance();

        return $container->bound(Intermidiate::class) ? $container->make(Intermidiate::class)->last($class) : null;
    }
}
