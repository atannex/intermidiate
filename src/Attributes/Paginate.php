<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Fills a paginator parameter with a page of models, when the data doesn't
 * already have one. The declared type picks the kind: LengthAwarePaginator
 * paginates, Paginator simple-paginates, CursorPaginator uses a cursor.
 * The current page comes from the query string, which the links keep.
 *
 *     #[Paginate(latest: 'published_at', with: 'author')]
 *     public readonly LengthAwarePaginator $posts,   // App\Models\Post, 15 a page
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Paginate extends Records
{
    /**
     * @param  class-string|null  $model
     * @param  string|list<string>  $with
     * @param  string|list<string>  $withCount
     * @param  string|list<string>  $scopes
     * @param  int|null  $perPage  default: the model's $perPage
     */
    public function __construct(
        ?string $model = null,
        public readonly ?int $perPage = null,
        string|array $with = [],
        string|array $withCount = [],
        ?string $latest = null,
        ?string $oldest = null,
        string|array $scopes = [],
        ?string $query = null,
        public readonly string $pageName = 'page',
    ) {
        parent::__construct($model, $with, $withCount, $latest, $oldest, $scopes, $query);
    }
}
