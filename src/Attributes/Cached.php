<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Keeps a member's value in the cache across requests. The key is built
 * from the class, the member, the Presenter's inputs (models by their key
 * and updated_at, so an edit shows at once) and the locale:
 *
 *     #[Cached(minutes: 10)]
 *     public function trending(): Collection
 *
 * Models are cached as they are, and wrapped in their presenter afterwards.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Cached
{
    /**
     * @param  int|null  $seconds  takes precedence over minutes; null and minutes null keep it forever
     * @param  string|null  $store  a cache store from config/cache.php
     * @param  list<string>  $tags  for stores that support tags, so Cache::tags(...)->flush() clears them
     */
    public function __construct(
        public readonly ?int $minutes = 60,
        public readonly ?int $seconds = null,
        public readonly ?string $store = null,
        public readonly array $tags = [],
    ) {}

    public function ttl(): ?int
    {
        return $this->seconds ?? ($this->minutes === null ? null : $this->minutes * 60);
    }
}
