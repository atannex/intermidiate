<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Presenting;

/**
 * @internal What the Engine has resolved for one presenter, so each member
 * is worked out once. Owned by one presenter for its lifetime; never shared.
 */
final class Memo
{
    /** @var array<string, mixed> member => value */
    public array $values = [];

    /** @var array<string, array{mixed, mixed}> wrapped property => [raw value, wrapped value] */
    public array $wrapped = [];
}
