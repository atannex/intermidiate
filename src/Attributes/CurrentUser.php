<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Fills a constructor parameter with the signed-in user. Nullable, it's
 * null for guests; not nullable, a guest is sent to log in.
 * A parameter named $user, $viewer or $currentUser typed with a user class
 * needs no attribute.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class CurrentUser
{
    /** @param  string|null  $guard  default guard when null */
    public function __construct(public readonly ?string $guard = null) {}
}
