<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Presenting;

use ArrayAccess;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Support\Member;
use Atannex\Intermidiate\Support\Schema;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * Presentation logic for one model (or array): computed fields as methods,
 * everything else read straight from the wrapped value.
 *
 *     final class StoryCard extends ModelPresenter
 *     {
 *         public function kicker(): string { return Str::upper($this->subject->category?->name ?? 'News'); }
 *     }
 *
 *     {{ $card->kicker }}  {{ $card->title }}   {{-- computed, then forwarded --}}
 *
 * Computed fields run once per presenter. A method of the subject can be
 * called through the presenter too.
 *
 * @template TSubject
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
abstract class ModelPresenter implements Arrayable, ArrayAccess, JsonSerializable
{
    /** @var array<string, mixed> */
    private array $computed = [];

    /**
     * @param  TSubject  $subject
     */
    final public function __construct(protected readonly mixed $subject) {}

    /**
     * @param  TSubject  $subject
     */
    public static function make(mixed $subject): static
    {
        return new static($subject);
    }

    /**
     * Wraps every item (a paginator's items too). Nulls are skipped.
     *
     * @param  iterable<TSubject>|Paginator|CursorPaginator  $subjects
     * @return Collection<int, static>
     */
    public static function collection(iterable|Paginator|CursorPaginator $subjects): Collection
    {
        $items = $subjects instanceof Paginator || $subjects instanceof CursorPaginator ? $subjects->items() : $subjects;

        return (new Collection($items))->filter(fn ($subject) => $subject !== null)->map(fn ($subject) => new static($subject))->values();
    }

    /** @return TSubject */
    public function unwrap(): mixed
    {
        return $this->subject;
    }

    public function __get(string $name): mixed
    {
        $member = $this->member($name);

        if ($member !== null) {
            return $this->compute($member);
        }

        return is_array($this->subject) ? ($this->subject[$name] ?? null) : $this->subject->{$name};
    }

    public function __isset(string $name): bool
    {
        if (($member = $this->member($name)) !== null) {
            return $this->compute($member) !== null;
        }

        return is_array($this->subject) ? isset($this->subject[$name]) : isset($this->subject->{$name});
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (! is_object($this->subject) || ! method_exists($this->subject, $method) && ! method_exists($this->subject, '__call')) {
            throw new PresenterException(static::class." has no method {$method}() and neither does its subject.");
        }

        return $this->subject->{$method}(...$arguments);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new PresenterException(static::class.' is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new PresenterException(static::class.' is read-only.');
    }

    /**
     * The subject's array form plus every computed field.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = match (true) {
            is_array($this->subject) => $this->subject,
            $this->subject instanceof Arrayable => $this->subject->toArray(),
            $this->subject instanceof JsonSerializable => (array) $this->subject->jsonSerialize(),
            default => [],
        };

        foreach (Schema::of($this)->members as $key => $member) {
            if ($member->kind !== Member::CALLABLE && ! $member->viewOnly) {
                $value = $this->compute($member);
                $data[$key] = $value instanceof Arrayable ? $value->toArray() : $value;
            }
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function member(string $name): ?Member
    {
        $member = Schema::of($this)->member($name);

        return $member !== null && $member->kind !== Member::CALLABLE ? $member : null;
    }

    private function compute(Member $member): mixed
    {
        if ($member->kind === Member::PROPERTY) {
            return $this->{$member->name};
        }

        if (! array_key_exists($member->name, $this->computed)) {
            $this->computed[$member->name] = $this->{$member->name}();
        }

        return $this->computed[$member->name];
    }
}
