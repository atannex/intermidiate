<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Calendar;

/** A column heading of a month grid. */
final class Weekday
{
    public function __construct(
        /** 0 (Sunday) to 6 (Saturday) */
        public readonly int $number,
        public readonly string $name,
        public readonly string $short,
        public readonly string $min,
        public readonly bool $weekend,
    ) {}
}
