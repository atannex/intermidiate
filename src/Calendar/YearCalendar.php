<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

use Atannex\Intermidiate\Support\Numeral;
use Atannex\Intermidiate\Support\Settings;
use Carbon\CarbonImmutable;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The twelve months of a year, each with its padded parameter, translated
 * names, whether it is the one being viewed and whether it has content.
 *
 * @implements Arrayable<int, mixed>
 * @implements IteratorAggregate<int, Month>
 */
final class YearCalendar implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    private ?int $current = null;

    /** @var array<int, int> */
    private array $counts = [];

    private bool $tracksFilled = false;

    private ?Closure $link = null;

    /** @var array<int, string> */
    private array $names = [];

    private ?string $locale = null;

    /** @var Collection<int, Month>|null */
    private ?Collection $months = null;

    public function __construct(public readonly int $year) {}

    /** The month being viewed: 3, "03" or null for none. */
    public function current(int|string|null $month): self
    {
        $this->current = $month === null || $month === '' ? null : (int) $month;

        return $this->fresh();
    }

    /**
     * Months that have content: a list (3, "03", "2026-03", dates) or a map
     * of month => count. Without this every month counts as filled.
     *
     * @param  iterable<mixed>|Arrayable<array-key, mixed>  $months
     */
    public function filled(iterable|Arrayable $months): self
    {
        $this->counts = Counts::normalize($months, 'Y', (string) $this->year);
        $this->tracksFilled = true;

        return $this->fresh();
    }

    /**
     * The link for each month; return null to leave a month unlinked.
     * Empty months get no link unless $empty is true.
     *
     * @param  Closure(Month): ?string  $link
     */
    public function link(Closure $link, bool $empty = false): self
    {
        $this->link = $empty ? $link : fn (Month $month) => $month->filled ? $link($month) : null;

        return $this->fresh();
    }

    /**
     * Your own month names, keyed 1 to 12 (short names are cut to 3 letters).
     *
     * @param  array<int|string, string>  $names
     */
    public function names(array $names): self
    {
        $this->names = [];

        foreach ($names as $number => $name) {
            $this->names[(int) $number] = $name;
        }

        return $this->fresh();
    }

    public function locale(string $locale): self
    {
        $this->locale = $locale;

        return $this->fresh();
    }

    /** @return Collection<int, Month> */
    public function months(): Collection
    {
        if ($this->months !== null) {
            return $this->months;
        }

        $locale = $this->locale ?? Settings::locale();
        $january = CarbonImmutable::create($this->year, 1, 1)->locale($locale);
        $months = [];

        foreach (range(1, 12) as $number) {
            $months[] = $this->month($number, $january->setDate($this->year, $number, 1), $locale);
        }

        return $this->months = new Collection($months);
    }

    public function find(int|string $month): ?Month
    {
        return $this->months()->get(((int) $month) - 1);
    }

    /** The month being viewed, if any. */
    public function selected(): ?Month
    {
        return $this->current === null ? null : $this->find($this->current);
    }

    /** How many months have content. */
    public function filledCount(): int
    {
        return $this->months()->where('filled', true)->count();
    }

    /** Total count across months (the map values, or one per listed month). */
    public function total(): int
    {
        return array_sum($this->counts);
    }

    public function previous(): int
    {
        return $this->year - 1;
    }

    public function next(): int
    {
        return $this->year + 1;
    }

    public function count(): int
    {
        return 12;
    }

    public function getIterator(): Traversable
    {
        return $this->months()->getIterator();
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->months()->map(fn (Month $month) => $month->toArray())->all();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function month(int $number, CarbonImmutable $start, string $locale): Month
    {
        [$name, $short] = isset($this->names[$number])
            ? [$this->names[$number], mb_substr($this->names[$number], 0, 3)]
            : Names::month($number, $locale);
        $count = $this->counts[$number] ?? 0;

        $month = new Month(
            $this->year,
            $number,
            Numeral::pad($number, 2),
            $name,
            $short,
            $number === $this->current,
            ! $this->tracksFilled || $count > 0,
            $count,
            null,
            $start,
        );

        if ($this->link === null) {
            return $month;
        }

        return new Month($month->year, $month->number, $month->param, $month->name, $month->short, $month->current, $month->filled, $month->count, ($this->link)($month), $month->start);
    }

    private function fresh(): self
    {
        $this->months = null;

        return $this;
    }
}
