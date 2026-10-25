<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

use Atannex\Intermidiate\Presenter;

final class SharedPresenter extends Presenter
{
    public function __construct(
        private readonly string $sharedTitle,
        private readonly Clockwork $navigation,
    ) {}

    public function headline(): string
    {
        return $this->sharedTitle.' '.$this->navigation->tick();
    }
}
