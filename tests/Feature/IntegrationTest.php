<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Cached;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;

final class IntegrationTest extends TestCase
{
    public function test_blade_reads_model_presenters_and_still_escapes(): void
    {
        $html = Blade::render('<h1>{{ $story->headline }}</h1><p>{{ $story["id"] }}</p>', ['story' => HeadlinePresenter::make(['id' => 7, 'title' => '<script>alert(1)</script>'])]);

        $this->assertSame('<h1>&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt;</h1><p>7</p>', $html);
    }

    public function test_a_blade_component_gets_presented_values_without_knowing_how_they_are_made(): void
    {
        Blade::component('headline', HeadlineComponent::class);

        $html = Blade::render('<x-headline :story="$story" />', ['story' => HeadlinePresenter::make(['id' => 7, 'title' => 'Rain'])]);

        $this->assertSame('<h2 id="story-7">RAIN</h2>', trim($html));
    }

    public function test_cached_members_are_kept_apart_per_user(): void
    {
        WhoPresenter::$runs = 0;

        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ayuk']));
        $this->assertSame('Ayuk', WhoPresenter::make()->who);

        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Ngum']));
        $this->assertSame('Ngum', WhoPresenter::make()->who, 'another user never gets the first user\'s value');
        $this->assertSame('Ngum', WhoPresenter::make()->who);

        $this->assertSame(2, WhoPresenter::$runs);
        $this->assertSame(WhoPresenter::make()->everyone, WhoPresenter::make()->everyone);
    }
}

/** @extends ModelPresenter<array<string, mixed>> */
final class HeadlinePresenter extends ModelPresenter
{
    public function headline(): string
    {
        return mb_strtoupper((string) $this->subject['title']);
    }
}

final class HeadlineComponent extends Component
{
    public function __construct(public HeadlinePresenter $story) {}

    public function render(): string
    {
        return '<h2 id="story-{{ $story->id }}">{{ $story->headline }}</h2>';
    }
}

final class WhoPresenter extends Presenter
{
    public static int $runs = 0;

    #[Cached(minutes: 5)]
    public function who(): string
    {
        self::$runs++;

        return (string) auth()->user()?->name;
    }

    #[Cached(minutes: 5, shared: true)]
    public function everyone(): string
    {
        return 'same for all';
    }
}
