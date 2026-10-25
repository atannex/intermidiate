<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

use Atannex\Intermidiate\Presenting\Presenter;

/**
 * @extends Presenter<Story>
 */
final class StoryCard extends Presenter
{
    public static int $kickers = 0;

    public function kicker(): string
    {
        self::$kickers++;

        return strtoupper($this->category ?? 'news');
    }

    public function readingTime(): string
    {
        return '3 min';
    }

    public function link(string $base): string
    {
        return $base.'/'.$this->id;
    }
}
