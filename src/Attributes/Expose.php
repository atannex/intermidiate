<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/** Exports a member under another variable name: #[Expose('stories')]. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Expose
{
    public function __construct(public readonly string $as) {}
}
