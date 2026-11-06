<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Presenters\Models;

use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Author;

/** Found by name for Author: no attribute, no config. */
final class AuthorPresenter extends ModelPresenter
{
    public static int $initials = 0;

    public function initials(): string
    {
        self::$initials++;

        return implode('', array_map(fn (string $word) => $word[0], explode(' ', $this->name)));
    }
}
