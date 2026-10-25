<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\ViewModels;

use Atannex\Intermidiate\Attributes\Expose;
use Atannex\Intermidiate\Attributes\Hidden;
use Atannex\Intermidiate\Attributes\ViewOnly;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\ViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class DeskViewModel extends ViewModel
{
    public static int $prepared = 0;

    public static int $computed = 0;

    public string $greeting = 'Hello';

    #[Hidden]
    public string $secret = 'hidden';

    public function __construct(
        public readonly Desk $desk,
        public readonly int $page = 1,
        public readonly ?CarbonImmutable $since = null,
        public readonly bool $compact = false,
        public readonly ?Request $request = null,
    ) {}

    public function title(): string
    {
        self::$computed++;

        return $this->desk->label();
    }

    #[Expose('pageLabel')]
    public function label(): string
    {
        return 'Page '.$this->page;
    }

    public function heading(string $suffix): string
    {
        return $this->title.' '.$suffix;
    }

    #[ViewOnly]
    public function viewOnly(): string
    {
        return 'markup';
    }

    #[Hidden]
    public function internal(): string
    {
        return 'internal';
    }

    protected function prepare(): void
    {
        self::$prepared++;
    }
}
