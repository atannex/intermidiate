<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Article extends Model
{
    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime', 'published' => 'bool', 'featured' => 'bool'];

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('featured', true);
    }
}
