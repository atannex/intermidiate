<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Pagination\PageLink;
use Atannex\Intermidiate\Pagination\Pages;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Pagination\Paginator;

final class PagesTest extends TestCase
{
    /** @return list<string> */
    private function labels(Pages $pages): array
    {
        return $pages->links()->map(fn (PageLink $link) => $link->gap ? '…' : ($link->current ? "[{$link->folio}]" : $link->folio))->all();
    }

    public function test_it_windows_the_page_links(): void
    {
        $pages = Pages::of($this->stories(200, page: 10));

        $this->assertSame(['01', '…', '08', '09', '[10]', '11', '12', '…', '20'], $this->labels($pages));
        $this->assertSame('10 / 20', $pages->fraction());
        $this->assertSame('91–100', $pages->range());
        $this->assertSame([10, 20, 200, 91, 100], [$pages->current(), $pages->last(), $pages->total(), $pages->from(), $pages->to()]);
        $this->assertSame('/archive/2026?page=9', $pages->previousUrl());
        $this->assertSame('/archive/2026?page=11', $pages->nextUrl());
    }

    public function test_a_single_skipped_page_is_shown_instead_of_a_gap(): void
    {
        $this->assertSame(['01', '02', '03', '[04]', '05', '06', '…', '20'], $this->labels(Pages::of($this->stories(200, page: 4))));
        $this->assertSame(['[01]', '02', '03'], $this->labels(Pages::of($this->stories(30))));
        $this->assertSame(['[1]', '2', '…', '20'], $this->labels(Pages::of($this->stories(200), window: 1, width: 1)));
    }

    public function test_page_numbers_widen_for_long_listings(): void
    {
        $pages = Pages::of($this->stories(5000, page: 7));

        $this->assertSame('007', $pages->folio());
        $this->assertSame('500', $pages->lastFolio());
        $this->assertSame('page', $pages->links()->firstWhere('current', true)->ariaCurrent());
    }

    public function test_simple_paginators_show_their_neighbours(): void
    {
        $paginator = new Paginator(range(1, 11), 10, 3, ['path' => '/wire']);
        $pages = Pages::of($paginator);

        $this->assertSame(['02', '[03]', '04'], $this->labels($pages));
        $this->assertNull($pages->last());
        $this->assertSame('03', $pages->fraction());
        $this->assertFalse($pages->onLastPage());
        $this->assertCount(3, $pages);
        $this->assertArrayHasKey('links', $pages->toArray());
    }
}
