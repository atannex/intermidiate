<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Presenters;

use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Support\Numeral;

/** Used inline by @presenter in a partial. */
final class BylinePresenter extends Presenter
{
    public function __construct(private readonly string $authorName, private readonly int $storyCount) {}

    public function byline(): string
    {
        return 'By '.$this->authorName;
    }

    public function count(): string
    {
        return Numeral::pad($this->storyCount);
    }
}
