<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Slicing;

use ArrayAccess;
use ArrayIterator;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Exceptions\UnknownMember;
use Countable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * The result of a partition: read groups as properties ($layout->lead),
 * by key ($layout['lead']) or with group('lead'). Loops over the groups.
 *
 * @implements ArrayAccess<string, Group>
 * @implements Arrayable<string, mixed>
 * @implements IteratorAggregate<string, Group>
 */
final class Partitioned implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  array<string, Group>  $groups
     */
    public function __construct(
        private readonly array $groups,
        private readonly Group $remainder,
        private readonly bool $applied,
        private readonly Paginator|CursorPaginator|null $source = null,
    ) {}

    public function group(string $name): Group
    {
        if ($name === 'remainder') {
            return $this->remainder;
        }

        return $this->groups[$name] ?? throw UnknownMember::for('The partition', $name, array_keys($this->groups));
    }

    /** Whether the group exists and has items. */
    public function has(string $name): bool
    {
        return isset($this->groups[$name]) && $this->groups[$name]->isNotEmpty();
    }

    /** @return array<string, Group> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** Items no group took (when there is no rest() group). */
    public function remainder(): Group
    {
        return $this->remainder;
    }

    /** False when a condition failed and everything went to the fallback group. */
    public function applied(): bool
    {
        return $this->applied;
    }

    /** The paginator the items came from, for links(). */
    public function source(): Paginator|CursorPaginator|null
    {
        return $this->source;
    }

    /** All items across groups. */
    public function count(): int
    {
        return array_sum(array_map('count', $this->groups)) + count($this->remainder);
    }

    public function __get(string $name): Group
    {
        return $this->group($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->groups[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->groups[$offset]);
    }

    public function offsetGet(mixed $offset): Group
    {
        return $this->group((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new PresenterException('A partition is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new PresenterException('A partition is read-only.');
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->groups);
    }

    /** @return array<string, list<mixed>> */
    public function toArray(): array
    {
        return array_map(fn (Group $group) => $group->toArray(), $this->groups);
    }

    public function jsonSerialize(): mixed
    {
        return array_map(fn (Group $group) => $group->jsonSerialize(), $this->groups);
    }
}
