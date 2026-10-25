<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Exceptions;

use Atannex\Intermidiate\Support\Suggest;

/** A required constructor parameter had no value in the given data. */
final class MissingViewData extends PresenterException
{
    /**
     * @param  list<string>  $available
     */
    public static function for(string $class, string $parameter, string $type, array $available): self
    {
        $keys = $available === [] ? 'none' : implode(', ', array_map(fn ($key) => '$'.$key, $available));

        return new self(sprintf(
            '%s needs $%s (%s), but the data has no "%s".%s Available: %s.',
            $class, $parameter, $type, $parameter, Suggest::hint($parameter, $available), $keys,
        ));
    }
}
