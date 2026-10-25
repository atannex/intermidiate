<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Writer extends Model
{
    protected $guarded = [];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
