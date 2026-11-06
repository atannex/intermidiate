<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

/** A plain service the container can build. */
final class Clockwork
{
    public function tick(): string
    {
        return 'tick';
    }
}
