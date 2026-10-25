<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Exceptions;

use Atannex\Intermidiate\Support\Suggest;

/** A view asked a Presenter, model presenter or partition for something it doesn't have. */
final class UnknownMember extends PresenterException
{
    /**
     * @param  list<string>  $known
     */
    public static function for(string $owner, string $name, array $known): self
    {
        return new self(sprintf(
            '%s has no "%s".%s It has: %s.',
            $owner, $name, Suggest::hint($name, $known), $known === [] ? 'nothing' : implode(', ', $known),
        ));
    }
}
