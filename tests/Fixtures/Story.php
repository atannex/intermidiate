<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

final class Story
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly bool $hasImage = false,
        public readonly ?string $category = null,
    ) {}

    public function headline(): string
    {
        return mb_strtoupper($this->title);
    }
}
