<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

/**
 * What #[Paginate] and #[Fetch] share: which model, how it's loaded and
 * ordered. Every option can be left out:
 *
 *  - the model comes from the parameter's type (`?Post $featured`) or its
 *    name (`$posts` → App\Models\Post);
 *  - the query can be narrowed by a static method named after the parameter
 *    (`postsQuery(Builder $query, int $year)`), which gets the other inputs
 *    by name.
 */
abstract class Records
{
    /** @var list<string> */
    public readonly array $with;

    /** @var list<string> */
    public readonly array $withCount;

    /** @var list<string> */
    public readonly array $scopes;

    /**
     * @param  class-string|null  $model
     * @param  string|list<string>  $with  relations to eager load
     * @param  string|list<string>  $withCount  relations to count ($post->comments_count)
     * @param  string|null  $latest  newest first by this column
     * @param  string|null  $oldest  oldest first by this column
     * @param  string|list<string>  $scopes  local scopes without arguments: 'published'
     * @param  string|null  $query  a static method of the Presenter that narrows the query (default: "{parameter}Query" when it exists)
     */
    public function __construct(
        public readonly ?string $model = null,
        string|array $with = [],
        string|array $withCount = [],
        public readonly ?string $latest = null,
        public readonly ?string $oldest = null,
        string|array $scopes = [],
        public readonly ?string $query = null,
    ) {
        $this->with = array_values((array) $with);
        $this->withCount = array_values((array) $withCount);
        $this->scopes = array_values((array) $scopes);
    }
}
