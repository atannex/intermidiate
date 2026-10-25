<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Exceptions;

/** A value couldn't be given to a constructor parameter of the declared type. */
final class InvalidViewData extends ViewModelException
{
    public static function for(string $class, string $parameter, string $type, mixed $value): self
    {
        return new self(sprintf('%s expects $%s to be %s, got %s.', $class, $parameter, $type, self::describe($value)));
    }

    public static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => 'string "'.(mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value).'"',
            is_int($value), is_float($value) => get_debug_type($value).' '.$value,
            default => get_debug_type($value),
        };
    }
}
