<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\ViewModels;

use Atannex\Intermidiate\Support\Numeral;
use Atannex\Intermidiate\ViewModel;

/** Used inline by @viewmodel in a partial. */
final class BylineViewModel extends ViewModel
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
