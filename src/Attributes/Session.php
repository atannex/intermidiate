<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/** Fills a constructor parameter from the session, such as a flash message: #[Session('status')] ?string $status. */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Session
{
    /** @param  string|null  $key  the session key, when it isn't the parameter's name */
    public function __construct(public readonly ?string $key = null) {}
}
