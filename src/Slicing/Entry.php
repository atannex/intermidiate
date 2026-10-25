<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Slicing;

use Atannex\Intermidiate\Support\Numeral;

/**
 * One item of a group, with its place on the page worked out:
 *
 *     @foreach ($ledger->entries() as $entry)
 *         <span>{{ $entry->folio }}</span> {{ $entry->item->title }}
 *
 *     @endforeach
 *
 * Unknown properties are read from the item, so `$entry->title` works too.
 */
final class Entry
{
    public readonly string $folio;

    public function __construct(
        public readonly mixed $item,
        /** 1-based number on the whole listing (page 2 of 20 a page starts at 21) */
        public readonly int $number,
        /** 0-based position inside the group */
        public readonly int $index,
        public readonly int $count,
        int $width,
    ) {
        $this->folio = Numeral::pad($number, $width);
    }

    public function first(): bool
    {
        return $this->index === 0;
    }

    public function last(): bool
    {
        return $this->index === $this->count - 1;
    }

    public function even(): bool
    {
        return $this->index % 2 === 1;
    }

    public function odd(): bool
    {
        return $this->index % 2 === 0;
    }

    public function __get(string $name): mixed
    {
        return is_array($this->item) ? ($this->item[$name] ?? null) : $this->item->{$name};
    }

    public function __isset(string $name): bool
    {
        return is_array($this->item) ? isset($this->item[$name]) : isset($this->item->{$name});
    }
}
