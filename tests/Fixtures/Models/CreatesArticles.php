<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The writers and articles tables, for tests on real Eloquent models. */
trait CreatesArticles
{
    protected function createArticles(): void
    {
        Schema::create('writers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->nullable();
            $table->string('title');
            $table->text('body');
            $table->boolean('published')->default(true);
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }
}
