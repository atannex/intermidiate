<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Exceptions\ViewModelException;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Atannex\Intermidiate\Tests\Fixtures\StoryCard;
use Atannex\Intermidiate\Tests\TestCase;

final class PresenterTest extends TestCase
{
    public function test_computed_fields_and_forwarding(): void
    {
        StoryCard::$kickers = 0;
        $card = StoryCard::make(new Story(4, 'Rain in Fontem', category: 'weather'));

        $this->assertSame('WEATHER', $card->kicker);
        $this->assertSame('WEATHER', $card['kicker']);
        $this->assertSame(1, StoryCard::$kickers, 'computed once');
        $this->assertSame('3 min', $card->reading_time);
        $this->assertSame('3 min', $card->readingTime);
        $this->assertSame('Rain in Fontem', $card->title);
        $this->assertSame('RAIN IN FONTEM', $card->headline());
        $this->assertSame('/s/4', $card->link('/s'));
        $this->assertTrue(isset($card->title));
        $this->assertInstanceOf(Story::class, $card->unwrap());
    }

    public function test_collections_and_arrays(): void
    {
        $cards = StoryCard::collection($this->stories(3));

        $this->assertCount(3, $cards);
        $this->assertContainsOnlyInstancesOf(StoryCard::class, $cards);

        $card = StoryCard::make(['id' => 9, 'title' => 'Array story']);
        $this->assertSame('Array story', $card->title);
        $this->assertSame(['id' => 9, 'title' => 'Array story', 'kicker' => 'NEWS', 'readingTime' => '3 min'], $card->toArray());
    }

    public function test_unknown_methods_are_explained(): void
    {
        $this->expectException(ViewModelException::class);

        StoryCard::make(new Story(1, 'x'))->nope();
    }
}
