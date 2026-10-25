<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Support\Format;
use Atannex\Intermidiate\Tests\TestCase;
use Carbon\CarbonImmutable;

final class FormatTest extends TestCase
{
    public function test_excerpts_are_plain_text_cut_at_a_word(): void
    {
        $html = '<p>Rain in <b>Fontem</b> closed&nbsp;the road.</p><p>Schools stay shut &amp; markets wait.</p>';

        $this->assertSame('Rain in Fontem closed the road. Schools stay shut & markets wait.', Format::excerpt($html, 200));
        $this->assertSame('Rain in Fontem closed the…', Format::excerpt($html, 28));
        $this->assertSame('', Format::excerpt(null));
    }

    public function test_initials_and_reading_time(): void
    {
        $this->assertSame('MN', Format::initials('Ma Ngum'));
        $this->assertSame('MT', Format::initials('Ma Eba Tabe'));
        $this->assertSame('N', Format::initials('ngum'));
        $this->assertSame('ME', Format::initials('Ma Eba Tabe', edges: false));
        $this->assertSame('FL', Format::initials('FC 2000 Lions', lettersOnly: true));
        $this->assertSame('EE', Format::initials('Essoh-Attah Eagles', edges: false, hyphens: false));
        $this->assertSame('EA', Format::initials('Essoh-Attah Eagles', edges: false));
        $this->assertSame(1, Format::readingTime('short'));
        $this->assertSame(3, Format::readingTime(str_repeat('word ', 401)));
    }

    public function test_dates_use_named_formats_the_timezone_and_the_locale(): void
    {
        config(['app.timezone' => 'Africa/Douala', 'intermidiate.dates' => ['card' => 'D j']]);
        $date = '2026-03-07 23:30:00 UTC';

        $this->assertSame('8 Mar 2026', Format::date($date));
        $this->assertSame('8 March 2026', Format::date($date, 'long'));
        $this->assertSame('Sun 8', Format::date($date, 'card'));
        $this->assertSame('2026-03-08', Format::date($date, 'Y-m-d'));
        $this->assertSame('2026-03-08T00:30:00+01:00', Format::iso($date));
        $this->assertNull(Format::date(null));

        $this->assertSame('7 Mar 2026, 23:30', Format::date($date, 'datetime', 'UTC'));
        $this->assertSame('2026-03-07T23:30:00+00:00', Format::iso($date, 'UTC'));

        app()->setLocale('fr');
        $this->assertSame('8 mars 2026', Format::date($date, 'long'));
        $this->assertSame('8 March 2026', Format::date($date, 'long', translate: false));
    }

    public function test_relative_dates_turn_absolute_after_a_while(): void
    {
        CarbonImmutable::setTestNow('2026-03-10 12:00:00');

        $this->assertSame('2 hours ago', Format::relative('2026-03-10 10:00:00'));
        $this->assertSame('1 Mar 2026', Format::relative('2026-03-01 10:00:00'));
        $this->assertSame('1 week ago', Format::relative('2026-03-01 10:00:00', days: null));
        $this->assertSame('2h ago', Format::relative('2026-03-10 10:00:00', short: true));

        CarbonImmutable::setTestNow();
    }

    public function test_counts(): void
    {
        $this->assertSame('1 story', Format::count(1, 'story'));
        $this->assertSame('3 stories', Format::count(3, 'story'));
        $this->assertSame('1,204 stories', Format::count(1204, 'story'));
        $this->assertSame('12,500', Format::number(12500));
        $this->assertSame('1204 stories', Format::count(1204, 'story', group: false));

        app('translator')->addLines(['*.:count story|:count stories' => ':count article|:count articles'], 'en');
        $this->assertSame('3 articles', Format::count(3, 'story'));
    }
}
