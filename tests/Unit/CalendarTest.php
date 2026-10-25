<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Calendar\Calendar;
use Atannex\Intermidiate\Calendar\Day;
use Atannex\Intermidiate\Calendar\Month;
use Atannex\Intermidiate\Tests\TestCase;
use Carbon\CarbonImmutable;

final class CalendarTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_year_has_twelve_padded_months(): void
    {
        $months = Calendar::year(2026)->current('03')->filled(['01', '03', '2026-11', '2025-07'])->months();

        $this->assertCount(12, $months);
        $this->assertSame('03', $months[2]->param);
        $this->assertSame('March', $months[2]->name);
        $this->assertSame('Mar', $months[2]->short);
        $this->assertTrue($months[2]->current);
        $this->assertFalse($months[1]->current);
        $this->assertSame([1, 3, 11], $months->where('filled', true)->pluck('number')->values()->all());
        $this->assertSame('2026-03', $months[2]->key());
        $this->assertSame('March 2026', $months[2]->label());
        $this->assertSame(['year' => 2026, 'month' => '03'], $months[2]->routeParameters());
    }

    public function test_without_filled_every_month_counts(): void
    {
        $this->assertSame(12, Calendar::year(2026)->filledCount());
    }

    public function test_counts_links_and_names(): void
    {
        $year = Calendar::year('2026')
            ->filled([3 => 12, 5 => 1])
            ->link(fn (Month $month) => "/archive/{$month->year}/{$month->param}")
            ->names([1 => 'Janvier', 3 => 'Mars']);

        $this->assertSame(12, $year->find(3)->count);
        $this->assertSame('/archive/2026/03', $year->find(3)->url);
        $this->assertNull($year->find(4)->url, 'empty months are not linked');
        $this->assertSame('Mars', $year->find(3)->name);
        $this->assertSame('Mar', $year->find(3)->short);
        $this->assertSame(13, $year->total());
        $this->assertSame(2, $year->filledCount());
        $this->assertNull($year->selected());
        $this->assertSame([2025, 2027], [$year->previous(), $year->next()]);

        $linked = Calendar::year(2026)->filled([3])->link(fn (Month $month) => 'x', empty: true);
        $this->assertSame('x', $linked->find(4)->url);
    }

    public function test_month_names_follow_the_locale(): void
    {
        $this->assertSame('mars', Calendar::year(2026)->locale('fr')->find(3)->name);
    }

    public function test_a_month_grid_starts_on_the_configured_weekday(): void
    {
        // March 2026 starts on a Sunday.
        $calendar = Calendar::month(2026, 3);
        $weeks = $calendar->weeks();

        $this->assertSame('Mon', $calendar->weekdays()->first()->short);
        $this->assertSame('2026-02-23', $weeks->first()->days()->first()->key());
        $this->assertFalse($weeks->first()->days()->first()->inMonth);
        $this->assertSame(1, $weeks->first()->days()->last()->number);
        $this->assertCount(6, $weeks);
        $this->assertTrue($weeks->every(fn ($week) => count($week) === 7));

        $sunday = Calendar::month(2026, 3)->weekStartsOn(0);
        $this->assertSame('Sun', $sunday->weekdays()->first()->short);
        $this->assertTrue($sunday->weekdays()->first()->weekend);
        $this->assertSame('2026-03-01', $sunday->weeks()->first()->days()->first()->key());
        $this->assertCount(5, $sunday->weeks());
    }

    public function test_days_are_padded_filled_and_linked(): void
    {
        CarbonImmutable::setTestNow('2026-03-10 12:00');

        $calendar = Calendar::month(2026, 3)
            ->current('07')
            ->filled(['2026-03-07', 9, '2026-04-07', CarbonImmutable::parse('2026-03-20')])
            ->link(fn (Day $day) => '/archive/'.implode('/', $day->routeParameters()));

        $days = $calendar->days()->keyBy('number');

        $this->assertSame('07', $days[7]->param);
        $this->assertTrue($days[7]->current);
        $this->assertTrue($days[7]->filled);
        $this->assertTrue($days[9]->filled);
        $this->assertTrue($days[20]->filled);
        $this->assertFalse($days[8]->filled);
        $this->assertSame('/archive/2026/03/07', $days[7]->url);
        $this->assertNull($days[8]->url);
        $this->assertTrue($days[10]->today);
        $this->assertTrue($days[7]->weekend);
        $this->assertSame('Saturday', $days[7]->weekday);
        $this->assertTrue($calendar->weeks()->contains(fn ($week) => $week->current()));
    }

    public function test_outside_days_can_be_left_out(): void
    {
        $first = Calendar::month(2026, 3)->withoutOutsideDays()->weeks()->first()->days();

        $this->assertNull($first->first());
        $this->assertSame(1, $first->last()->number);
    }

    public function test_navigation_between_months(): void
    {
        $calendar = Calendar::at(CarbonImmutable::parse('2026-01-15'));

        $this->assertSame([2025, 12], [$calendar->previous()->year, $calendar->previous()->month]);
        $this->assertSame([2026, 2], [$calendar->next()->year, $calendar->next()->month]);
        $this->assertTrue($calendar->days()[14]->current);
        $this->assertSame('January 2026', $calendar->month()->label());
        $this->assertTrue($calendar->month()->current);
        $this->assertSame(31, $calendar->month()->days());
        $this->assertArrayHasKey('weeks', $calendar->toArray());
    }
}
