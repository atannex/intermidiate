<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Fills a constructor parameter from the query string when the data doesn't
 * have it: `?sort=popular` → `#[Query] public readonly Sort $sort = Sort::Latest`.
 * A value that doesn't fit the type falls back to the default (or null), so
 * a hand-edited URL can't break the page.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Query
{
    /** @param  string|null  $key  the query key, when it isn't the parameter's name (snake_case works too) */
    public function __construct(public readonly ?string $key = null) {}
}
