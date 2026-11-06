<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

enum Desk: string
{
    case News = 'news';
    case Sports = 'sports';
    case Economy = 'economy';

    public function label(): string
    {
        return ucfirst($this->value).' desk';
    }
}
