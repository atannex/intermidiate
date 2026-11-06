<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

final class Editor
{
    public function __construct(public readonly int $id, public readonly string $name) {}
}
