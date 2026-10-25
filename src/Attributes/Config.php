<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/** Fills a constructor parameter from config, when the data doesn't have it: #[Config('app.name')] string $siteName. */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Config
{
    public function __construct(public readonly string $key) {}
}
