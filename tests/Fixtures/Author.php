<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

class Author
{
    public function __construct(public readonly int $id, public readonly string $name) {}
}
