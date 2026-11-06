<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Support\Numeral;
use Atannex\Intermidiate\Tests\TestCase;
use InvalidArgumentException;

final class NumeralTest extends TestCase
{
    public function test_it_pads_numbers(): void
    {
        $this->assertSame('03', Numeral::pad(3));
        $this->assertSame('003', Numeral::pad('3', 3));
        $this->assertSame('12', Numeral::pad(12));
        $this->assertSame('120', Numeral::pad(120));
        $this->assertSame('-03', Numeral::pad(-3));
        $this->assertSame('', Numeral::pad(null));
    }

    public function test_the_default_width_comes_from_config(): void
    {
        config(['intermidiate.pad' => 3]);

        $this->assertSame('007', Numeral::pad(7));
    }

    public function test_fractions_widen_to_the_total(): void
    {
        $this->assertSame('03 / 12', Numeral::fraction(3, 12));
        $this->assertSame('003 / 120', Numeral::fraction(3, 120));
        $this->assertSame('1·4', Numeral::fraction(1, 4, 1, '·'));
    }

    public function test_roman_numerals(): void
    {
        $this->assertSame('MMXXVI', Numeral::roman(2026));
        $this->assertSame('XIV', Numeral::roman(14));

        $this->expectException(InvalidArgumentException::class);
        Numeral::roman(0);
    }

    public function test_percentages_and_bars(): void
    {
        $this->assertSame(25, Numeral::percent(1, 4));
        $this->assertSame(33.3, Numeral::percent(1, 3, 1));
        $this->assertSame(0, Numeral::percent(5, 0));
        $this->assertSame('25%', Numeral::bar(30, 120));
        $this->assertSame('4%', Numeral::bar(1, 1000, minimum: 4));
        $this->assertSame('0%', Numeral::bar(0, 1000, minimum: 4));
    }
}
