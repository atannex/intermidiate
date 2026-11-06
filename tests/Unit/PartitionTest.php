<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Exceptions\UnknownMember;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Slicing\Partition;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Atannex\Intermidiate\Tests\TestCase;

final class PartitionTest extends TestCase
{
    public function test_it_cuts_a_page_into_lead_beside_and_ledger(): void
    {
        $layout = Partition::of($this->stories(10))->take('lead', 1)->take('beside', 2)->rest('ledger')->get();

        $this->assertTrue($layout->applied());
        $this->assertSame(1, $layout->lead->first()->id);
        $this->assertSame([2, 3], $layout->beside->pluck('id')->all());
        $this->assertSame([4, 5, 6, 7, 8, 9, 10], $layout['ledger']->pluck('id')->all());
        $this->assertSame(10, $layout->count());
        $this->assertSame(['lead', 'beside', 'ledger'], array_keys($layout->groups()));
    }

    public function test_numbers_follow_the_paginator(): void
    {
        $layout = Partition::of($this->stories(25, page: 3))->take('lead', 1)->take('beside', 2)->rest('ledger')->get();

        $this->assertSame(21, $layout->lead->start());
        $this->assertSame(24, $layout->ledger->start());
        $this->assertSame(25, $layout->ledger->end());
        $this->assertSame('24', $layout->ledger->folio(0));
        $this->assertSame('24–25', $layout->ledger->range());

        $entries = $layout->ledger->entries();
        $this->assertSame(['24', '25'], $entries->pluck('folio')->all());
        $this->assertTrue($entries->first()->first());
        $this->assertTrue($entries->last()->last());
        $this->assertSame('Story 24', $entries->first()->title);
    }

    public function test_a_test_picks_matching_items_wherever_they_are(): void
    {
        $layout = Partition::of($this->stories(10))
            ->take('lead', 1, fn (Story $story) => $story->hasImage)
            ->rest('ledger')
            ->get();

        $this->assertSame(3, $layout->lead->first()->id);
        $this->assertSame(3, $layout->lead->start());
        $this->assertSame([1, 2, 4, 5], $layout->ledger->take(4)->pluck('id')->all());
        $this->assertSame([1, 2, 4], $layout->ledger->entries()->take(3)->pluck('number')->all());
    }

    public function test_too_few_items_fall_back_to_the_rest_group(): void
    {
        $layout = Partition::of($this->stories(2))->take('lead', 1)->take('beside', 2)->rest('ledger')->atLeast(3)->get();

        $this->assertFalse($layout->applied());
        $this->assertNull($layout->lead->first());
        $this->assertTrue($layout->beside->isEmpty());
        $this->assertFalse($layout->has('lead'));
        $this->assertSame([1, 2], $layout->ledger->pluck('id')->all());
        $this->assertSame(1, $layout->ledger->start());
    }

    public function test_conditions_and_named_fallbacks(): void
    {
        $page = $this->stories(30, page: 2);

        $layout = Partition::of($page)->take('lead', 1)->take('rows', 100)->when($page->onFirstPage(), otherwise: 'rows')->get();

        $this->assertTrue($layout->lead->isEmpty());
        $this->assertCount(10, $layout->rows);
        $this->assertSame('11', $layout->rows->folio(0));
    }

    public function test_items_no_group_takes_are_the_remainder(): void
    {
        $layout = Partition::of(range(1, 5))->take('top', 2)->get();

        $this->assertSame([1, 2], $layout->top->all());
        $this->assertSame([3, 4, 5], $layout->remainder()->all());
        $this->assertSame(3, $layout->group('remainder')->start());
    }

    public function test_unique_start_and_width(): void
    {
        $story = new Story(1, 'One');
        $layout = Partition::of([$story, $story, new Story(2, 'Two')])->unique()->rest('all')->startAt(100)->pad(4)->get();

        $this->assertCount(2, $layout->all);
        $this->assertSame('0100', $layout->all->folio(0));

        $layout = Partition::of([['id' => 1], ['id' => 1], ['id' => 2]])->unique('id')->rest('all')->get();
        $this->assertCount(2, $layout->all);
    }

    public function test_groups_split_into_columns_and_chunks_keeping_numbers(): void
    {
        $group = Partition::of($this->stories(17, page: 2))->rest('ledger')->get()->ledger;

        $columns = $group->columns(3);
        $this->assertCount(3, $columns);
        $this->assertSame([3, 3, 1], $columns->map->count()->all());
        $this->assertSame([11, 14, 17], $columns->map->start()->all());

        $chunks = $group->chunk(4);
        $this->assertSame([4, 3], $chunks->map->count()->all());
        $this->assertInstanceOf(Group::class, $chunks->first());

        $this->assertSame([11, 12], $group->filter(fn (Story $story) => $story->id < 13)->entries()->pluck('number')->all());
        $this->assertSame(13, $group->skip(2)->start());
    }

    public function test_groups_forward_collection_methods_and_serialize(): void
    {
        $layout = Partition::of(range(1, 4))->take('top', 2)->rest('rest')->get();

        $this->assertSame(3, $layout->top->sum());
        $this->assertSame([1, 2], iterator_to_array($layout->top));
        $this->assertSame('{"top":[1,2],"rest":[3,4]}', json_encode($layout));
        $this->assertSame(['top' => [1, 2], 'rest' => [3, 4]], $layout->toArray());
    }

    public function test_mistakes_are_explained(): void
    {
        try {
            Partition::of([])->take('lead', 1)->rest('ledger')->get()->leed;
            $this->fail('Expected an unknown group.');
        } catch (UnknownMember $e) {
            $this->assertStringContainsString('Did you mean "lead"?', $e->getMessage());
        }

        $this->expectException(PresenterException::class);
        $this->expectExceptionMessage('already has a "lead" group');
        Partition::of([])->take('lead', 1)->take('lead', 2);
    }

    public function test_a_fallback_needs_somewhere_to_go(): void
    {
        $this->expectExceptionMessage('no rest() group');

        Partition::of([1])->take('lead', 1)->atLeast(3)->get();
    }
}
