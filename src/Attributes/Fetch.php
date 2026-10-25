<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Fills a parameter with models, when the data doesn't already have them.
 * The declared type picks the shape: a Collection (or array) gets every
 * match up to $limit, a model type gets the first one (or null).
 *
 *     #[Fetch(latest: 'published_at', limit: 5)]
 *     public readonly Collection $headlines,   // needs model: Post::class, the name doesn't say it
 *
 *     #[Fetch(scopes: 'featured')]
 *     public readonly ?Post $featured,
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Fetch extends Records
{
    /**
     * @param  class-string|null  $model
     * @param  string|list<string>  $with
     * @param  string|list<string>  $withCount
     * @param  string|list<string>  $scopes
     */
    public function __construct(
        ?string $model = null,
        public readonly ?int $limit = null,
        string|array $with = [],
        string|array $withCount = [],
        ?string $latest = null,
        ?string $oldest = null,
        string|array $scopes = [],
        ?string $query = null,
    ) {
        parent::__construct($model, $with, $withCount, $latest, $oldest, $scopes, $query);
    }
}
