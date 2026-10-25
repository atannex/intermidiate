<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atannex\Intermidiate\Intermidiate bind(string|array $views, string $viewModel)
 * @method static array bindings()
 * @method static array discovered()
 * @method static \Atannex\Intermidiate\ViewModel|null last(string $class)
 *
 * @see \Atannex\Intermidiate\Intermidiate
 */
final class Intermidiate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atannex\Intermidiate\Intermidiate::class;
    }
}
