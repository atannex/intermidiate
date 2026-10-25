<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/** Fills a constructor parameter with the flashed form input after a failed submit: #[Old] ?string $email. */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Old
{
    /** @param  string|null  $key  the input name, when it isn't the parameter's (snake_case works too) */
    public function __construct(public readonly ?string $key = null) {}
}
