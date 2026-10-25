<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Extra\Components;

use Atannex\Intermidiate\Presenter;

/** components/badge.blade.php finds it with a bare @presenter. */
final class BadgePresenter extends Presenter
{
    public function __construct(private readonly string $label) {}

    public function text(): string
    {
        return strtoupper($this->label);
    }
}
