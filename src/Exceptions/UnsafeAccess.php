<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Exceptions;

/**
 * Presenting would have queried or exposed something nothing declared: an
 * unloaded relation, a raw model, an attribute the model hides, a value
 * with no serialized form, or an object graph too deep or circular.
 */
final class UnsafeAccess extends PresenterException
{
    public static function hidden(string $presenter, string $model, string $attribute): self
    {
        return new self(sprintf(
            '%s lists "%s" in $visible, but %s hides it from serialization. Expose it through a method if publishing it is intended.',
            $presenter, $attribute, $model,
        ));
    }

    public static function unloaded(string $presenter, string $model, string $relation): self
    {
        return new self(sprintf(
            '%s reads %s::$%s, which is not loaded. Load it first (->load(\'%s\') or ->with(\'%s\')); a presenter doesn\'t lazy load (config intermidiate.strict).',
            $presenter, $model, $relation, $relation, $relation,
        ));
    }

    public static function model(string $where, string $class): self
    {
        return new self(sprintf(
            '%s would serialize a raw %s, exposing every visible attribute. Give it a model presenter (only what its $visible lists leaves) or map it in a method.',
            $where, $class,
        ));
    }

    public static function unsupported(string $where, mixed $value): self
    {
        return new self(sprintf('%s can\'t serialize %s. Convert it in a method.', $where, get_debug_type($value)));
    }

    public static function circular(string $where, string $class): self
    {
        return new self("{$where} reached the same {$class} again while serializing it: the object graph is circular.");
    }

    public static function depth(string $where, int $limit): self
    {
        return new self("{$where} nests deeper than {$limit} levels while serializing.");
    }
}
