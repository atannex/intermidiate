<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Presenting;

use Atannex\Intermidiate\Exceptions\UnsafeAccess;
use Atannex\Intermidiate\Support\Deferred;
use Atannex\Intermidiate\Support\Member;
use Atannex\Intermidiate\Support\MemberCache;
use Atannex\Intermidiate\Support\Schema;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use JsonSerializable;
use Stringable;
use Traversable;
use UnitEnum;

/**
 * @internal The one place presenters are executed. Presenter view data and
 * JSON, model presenters' computed fields and @presenter all read their
 * members here (once each, memoized) and serialize here.
 *
 * Serializing, strict (config intermidiate.strict), refuses what would
 * expose undeclared data: raw Eloquent models, closures and objects it can
 * only read by their public properties. Model presenters serialize their
 * declared fields only. Depth is bounded and cycles fail.
 */
final class Engine
{
    public const MAX_DEPTH = 32;

    /**
     * The members' values for a view: models wrapped, #[Lazy] members
     * Deferred, callable members as closures. For JSON, callables and
     * #[ViewOnly] members are left out.
     *
     * @return array<string, mixed>
     */
    public static function values(Schema $schema, object $presenter, Memo $memo, bool $json = false): array
    {
        $values = [];

        foreach ($schema->members as $key => $member) {
            if ($json && ($member->kind === Member::CALLABLE || $member->viewOnly)) {
                continue;
            }

            $values[$key] = $member->lazy
                ? new Deferred(fn () => self::value($member, $presenter, $memo))
                : self::value($member, $presenter, $memo);
        }

        return $values;
    }

    /**
     * One member's value, worked out once per presenter. A page
     * Presenter's members reach the view with their models wrapped in
     * their model presenter (unless #[Raw]).
     */
    public static function value(Member $member, object $presenter, Memo $memo): mixed
    {
        $name = $member->name;
        $wraps = $member->present !== false && ! $presenter instanceof ModelPresenter;

        if ($member->kind === Member::PROPERTY) {
            $raw = $presenter->{$name};

            if (! $wraps) {
                return $raw;
            }

            // Wrapped again only when the property changed.
            if (isset($memo->wrapped[$name]) && $memo->wrapped[$name][0] === $raw) {
                return $memo->wrapped[$name][1];
            }

            $memo->wrapped[$name] = [$raw, Models::wrapMember($raw, $member->present)];

            return $memo->wrapped[$name][1];
        }

        // Built fresh each time: kept on the memo it would be a cycle that only the GC frees.
        if ($member->kind === Member::CALLABLE) {
            return $presenter->{$name}(...);
        }

        if (! array_key_exists($name, $memo->values)) {
            $value = $member->cached !== null ? MemberCache::remember($presenter, $name, $member->cached) : $presenter->{$name}();
            $memo->values[$name] = $wraps ? Models::wrapMember($value, $member->present) : $value;
        }

        return $memo->values[$name];
    }

    /**
     * A value as JSON-compatible data (null, scalars and arrays), using each
     * value's own serialization and leaving the encoding to json_encode.
     *
     * @param  string  $where  what is being serialized, for messages
     */
    public static function serialize(mixed $value, bool $strict, string $where): mixed
    {
        return self::walk($value, $strict, $where, 0, []);
    }

    /**
     * @param  array<int, true>  $visiting  ids of the objects being walked
     */
    private static function walk(mixed $value, bool $strict, string $where, int $depth, array $visiting): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : throw UnsafeAccess::unsupported($where, $value);
        }

        if ($depth > self::MAX_DEPTH) {
            throw UnsafeAccess::depth($where, self::MAX_DEPTH);
        }

        if (is_array($value)) {
            return self::each($value, $strict, $where, $depth, $visiting);
        }

        if (! is_object($value)) {
            throw UnsafeAccess::unsupported($where, $value);
        }

        if (isset($visiting[$id = spl_object_id($value)])) {
            throw UnsafeAccess::circular($where, $value::class);
        }

        $visiting[$id] = true;
        $next = fn (mixed $inner) => self::walk($inner, $strict, $where, $depth + 1, $visiting);

        return match (true) {
            $value instanceof Deferred => $next($value->value()),
            $value instanceof ModelPresenter => $next($value->declaredFields($strict)),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value instanceof JsonSerializable ? $value->jsonSerialize() : $value->format(DATE_ATOM),
            $value instanceof Model => $strict ? throw UnsafeAccess::model($where, $value::class) : $next($value->toArray()),
            $value instanceof Closure => $strict ? throw UnsafeAccess::unsupported($where, $value) : null,
            ($value instanceof Paginator || $value instanceof CursorPaginator) && $value instanceof Arrayable => self::paginator($value, $next),
            $value instanceof Enumerable => self::each($value->all(), $strict, $where, $depth, $visiting),
            $value instanceof JsonSerializable => $next($value->jsonSerialize()),
            $value instanceof Arrayable => $next($value->toArray()),
            $value instanceof Htmlable => $value->toHtml(),
            $value instanceof Stringable => (string) $value,
            $value instanceof Traversable => self::each(iterator_to_array($value), $strict, $where, $depth, $visiting),
            default => $strict ? throw UnsafeAccess::unsupported($where, $value) : $next(get_object_vars($value)),
        };
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @param  array<int, true>  $visiting
     * @return array<array-key, mixed>
     */
    private static function each(array $items, bool $strict, string $where, int $depth, array $visiting): array
    {
        foreach ($items as $key => $item) {
            // Nothing to do for most values.
            if ($item !== null && ! is_bool($item) && ! is_int($item) && ! is_string($item)) {
                $items[$key] = self::walk($item, $strict, $where, $depth + 1, $visiting);
            }
        }

        return $items;
    }

    /**
     * A paginator's own array form (links, page numbers), with its items
     * walked here so strict mode sees them.
     *
     * @param  Paginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $paginator
     * @param  Closure(mixed): mixed  $next
     * @return array<string, mixed>
     */
    private static function paginator(Paginator|CursorPaginator $paginator, Closure $next): array
    {
        $array = $paginator instanceof Arrayable ? $paginator->toArray() : [];
        $array['data'] = $next(array_values($paginator->items()));

        return $array;
    }
}
