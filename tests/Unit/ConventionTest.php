<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Support\Convention;
use PHPUnit\Framework\TestCase;

final class ConventionTest extends TestCase
{
    private const ROOTS = ['App\\Presenters' => '/app/Presenters', 'Modules\\Blog\\Presenters' => '/modules/Blog/Presenters'];

    public function test_the_path_below_the_namespace_names_the_view(): void
    {
        $this->assertSame(['archive'], Convention::views('App\\Presenters\\ArchivePresenter', self::ROOTS));
        $this->assertSame(['archive.month'], Convention::views('App\\Presenters\\Archive\\MonthPresenter', self::ROOTS));
        $this->assertSame(['layouts.app'], Convention::views('App\\Presenters\\Layouts\\AppPresenter', self::ROOTS));
        $this->assertSame(['posts.show'], Convention::views('Modules\\Blog\\Presenters\\Posts\\ShowPresenter', self::ROOTS));
        $this->assertSame(['sidebar'], Convention::views('App\\Presenters\\Sidebar', self::ROOTS));
    }

    public function test_multi_word_names_try_kebab_then_snake_case(): void
    {
        $this->assertSame(['monthly-archive', 'monthly_archive'], Convention::views('App\\Presenters\\MonthlyArchivePresenter', self::ROOTS));

        $this->assertSame('monthly_archive', Convention::view('App\\Presenters\\MonthlyArchivePresenter', self::ROOTS, fn (string $view) => $view === 'monthly_archive'));
        $this->assertNull(Convention::view('App\\Presenters\\MonthlyArchivePresenter', self::ROOTS, fn () => false));
    }

    public function test_classes_outside_the_namespaces_use_their_basename(): void
    {
        $this->assertSame(['shared'], Convention::views('Elsewhere\\Deep\\SharedPresenter', self::ROOTS));
    }
}
