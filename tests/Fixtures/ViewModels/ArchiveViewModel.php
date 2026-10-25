<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\ViewModels;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Attributes\Lazy;
use Atannex\Intermidiate\Calendar\Calendar;
use Atannex\Intermidiate\Calendar\Month;
use Atannex\Intermidiate\Pagination\Pages;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Slicing\Partitioned;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Atannex\Intermidiate\ViewModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[ForView('archive')]
final class ArchiveViewModel extends ViewModel
{
    public static int $related = 0;

    /**
     * @param  LengthAwarePaginator<int, Story>  $posts
     * @param  list<string>  $monthsWithPosts
     */
    public function __construct(
        public readonly LengthAwarePaginator $posts,
        public readonly int $year,
        public readonly ?int $month = null,
        private readonly array $monthsWithPosts = [],
    ) {}

    public function lead(): ?Story
    {
        return $this->layout()->lead->first();
    }

    public function beside(): Group
    {
        return $this->layout()->beside;
    }

    public function ledger(): Group
    {
        return $this->layout()->ledger;
    }

    public function period(): string
    {
        return $this->month === null ? (string) $this->year : Calendar::year($this->year)->find($this->month)->label();
    }

    /** @return Collection<int, Month> */
    public function calendar(): Collection
    {
        return Calendar::year($this->year)
            ->current($this->month)
            ->filled($this->monthsWithPosts)
            ->link(fn (Month $month) => '/archive/'.$month->year.'/'.$month->param)
            ->months();
    }

    public function pages(): Pages
    {
        return Pages::of($this->posts);
    }

    #[Lazy]
    public function related(): Collection
    {
        self::$related++;

        return new Collection(['one', 'two']);
    }

    private function layout(): Partitioned
    {
        return $this->once('layout', fn () => $this->partition($this->posts)
            ->take('lead', 1)
            ->take('beside', 2)
            ->rest('ledger')
            ->atLeast(3)
            ->get());
    }
}
