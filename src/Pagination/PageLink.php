<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Pagination;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One cell of a pager: a page or a gap ("…").
 *
 * @implements Arrayable<string, mixed>
 */
final class PageLink implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly ?int $number,
        public readonly string $folio,
        public readonly ?string $url,
        public readonly bool $current,
        public readonly bool $gap = false,
    ) {}

    public static function gap(): self
    {
        return new self(null, '…', null, false, true);
    }

    /** "page" on the current page, for aria-current. */
    public function ariaCurrent(): ?string
    {
        return $this->current ? 'page' : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['number' => $this->number, 'folio' => $this->folio, 'url' => $this->url, 'current' => $this->current, 'gap' => $this->gap];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
