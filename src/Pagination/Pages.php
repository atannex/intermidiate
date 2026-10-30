<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Pagination;

use ArrayIterator;
use Atannex\Intermidiate\Support\Numeral;
use Atannex\Intermidiate\Support\Settings;
use Countable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Everything a pager prints, worked out: padded page numbers ("02 / 05"),
 * the item range, previous/next links and a windowed list of page links
 * with gaps and the current page marked.
 *
 *     <nav>@foreach ($pages as $link)
 *
 *         {{-- a gap --}} @if ($link->gap) <span>…</span>
 *
 *         @else <a href="{{ $link->url }}" aria-current="{{ $link->ariaCurrent() }}">{{ $link->folio }}</a>
 *
 *         @endif
 *
 *     @endforeach
 *
 * @implements Arrayable<string, mixed>
 * @implements IteratorAggregate<int, PageLink>
 */
final class Pages implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    private readonly int $width;

    private function __construct(
        private readonly Paginator|CursorPaginator $paginator,
        private readonly int $window,
        ?int $width,
    ) {
        $this->width = $width ?? max(Settings::pad(), strlen((string) ($this->last() ?? 0)));
    }

    /** @param  int  $window  pages shown either side of the current one */
    public static function of(Paginator|CursorPaginator $paginator, int $window = 2, ?int $width = null): self
    {
        return new self($paginator, max(0, $window), $width);
    }

    public function current(): ?int
    {
        return $this->paginator instanceof Paginator ? $this->paginator->currentPage() : null;
    }

    /** The last page, when the paginator knows it. */
    public function last(): ?int
    {
        return $this->paginator instanceof LengthAwarePaginator ? max(1, $this->paginator->lastPage()) : null;
    }

    public function total(): ?int
    {
        return $this->paginator instanceof LengthAwarePaginator ? $this->paginator->total() : null;
    }

    public function from(): ?int
    {
        return $this->paginator instanceof Paginator ? $this->paginator->firstItem() : null;
    }

    public function to(): ?int
    {
        return $this->paginator instanceof Paginator ? $this->paginator->lastItem() : null;
    }

    public function hasPages(): bool
    {
        return $this->paginator->hasPages();
    }

    public function onFirstPage(): bool
    {
        return $this->paginator instanceof Paginator ? $this->paginator->currentPage() <= 1 : $this->paginator->previousPageUrl() === null;
    }

    public function onLastPage(): bool
    {
        return $this->paginator instanceof Paginator ? ! $this->paginator->hasMorePages() : $this->paginator->nextPageUrl() === null;
    }

    /** The current page, padded: "02". */
    public function folio(): string
    {
        return Numeral::pad($this->current(), $this->width);
    }

    /** The last page, padded: "05". */
    public function lastFolio(): string
    {
        return Numeral::pad($this->last(), $this->width);
    }

    /** "02 / 05" (or just "02" when the last page is unknown). */
    public function fraction(string $separator = ' / '): string
    {
        $last = $this->last();

        return $last === null ? $this->folio() : Numeral::fraction((int) $this->current(), $last, $this->width, $separator);
    }

    /** "21–40", the items on this page. */
    public function range(string $separator = '–'): string
    {
        [$from, $to] = [$this->from(), $this->to()];

        return $from === null ? '' : ($from === $to ? (string) $from : $from.$separator.$to);
    }

    public function previousUrl(): ?string
    {
        return $this->paginator->previousPageUrl();
    }

    public function nextUrl(): ?string
    {
        return $this->paginator->nextPageUrl();
    }

    /**
     * Page links: the first and last page, the window around the current
     * one, and a gap wherever pages are skipped (a single skipped page is
     * shown instead of a gap). Without a known last page: previous, current
     * and next only.
     *
     * @return Collection<int, PageLink>
     */
    public function links(): Collection
    {
        $current = $this->current();

        if ($current === null) {
            return new Collection;
        }

        $last = $this->last() ?? ($this->paginator->hasMorePages() ? $current + 1 : $current);
        $shown = [1, $last];

        for ($page = $current - $this->window; $page <= $current + $this->window; $page++) {
            $shown[] = $page;
        }

        if ($this->last() === null) {
            $shown = [$current - 1, $current, $current + 1];
        }

        $shown = array_values(array_unique(array_filter($shown, fn (int $page) => $page >= 1 && $page <= $last)));
        sort($shown);

        $links = [];
        $previous = null;

        foreach ($shown as $page) {
            if ($previous !== null && $page - $previous === 2) {
                $links[] = $this->link($previous + 1, $current);
            } elseif ($previous !== null && $page - $previous > 2) {
                $links[] = PageLink::gap();
            }

            $links[] = $this->link($page, $current);
            $previous = $page;
        }

        return new Collection($links);
    }

    public function count(): int
    {
        return $this->links()->count();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->links()->all());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'current' => $this->current(),
            'last' => $this->last(),
            'total' => $this->total(),
            'from' => $this->from(),
            'to' => $this->to(),
            'fraction' => $this->fraction(),
            'previous' => $this->previousUrl(),
            'next' => $this->nextUrl(),
            'links' => $this->links()->map(fn (PageLink $link) => $link->toArray())->all(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function link(int $page, int $current): PageLink
    {
        return new PageLink($page, Numeral::pad($page, $this->width), $this->paginator->url($page), $page === $current);
    }
}
