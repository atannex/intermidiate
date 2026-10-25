<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Present;
use Atannex\Intermidiate\Attributes\Raw;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Facades\Intermidiate;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Tests\Fixtures\Author;
use Atannex\Intermidiate\Tests\Fixtures\Columnist;
use Atannex\Intermidiate\Tests\Fixtures\Editor;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\Models\AuthorPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\Models\EditorCard;
use Atannex\Intermidiate\Tests\Fixtures\Story;
use Atannex\Intermidiate\Tests\Fixtures\StoryCard;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

final class ModelPresentingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/authors/{author}', fn () => '')->name('authors.show');
        Route::get('/staff', fn () => StaffPresenter::make(authors: $this->authors()));
    }

    public function test_model_presenters_are_found_by_name_attribute_parent_or_map(): void
    {
        $models = Intermidiate::models();

        $this->assertSame(AuthorPresenter::class, $models->for(Author::class));
        $this->assertSame(AuthorPresenter::class, $models->for(new Columnist(1, 'Kola Ndi')));
        $this->assertSame(EditorCard::class, $models->for(Editor::class));
        $this->assertNull($models->for(Story::class));

        Intermidiate::present(Story::class, StoryCard::class);
        $this->assertSame(StoryCard::class, $models->for(Story::class));
    }

    public function test_members_reach_the_view_presented_with_no_code(): void
    {
        AuthorPresenter::$initials = 0;
        $presenter = StaffPresenter::make(authors: $this->authors());

        $this->assertInstanceOf(AuthorPresenter::class, $presenter->lead);
        $this->assertSame('MN', $presenter->lead->initials);
        $this->assertSame($presenter->lead, $presenter->toArray()['lead'], 'presented once, the same object everywhere');
        $presenter->lead->initials;
        $this->assertSame(1, AuthorPresenter::$initials, 'computed fields stay memoized');

        // A paginator keeps its links and totals, a group its numbers.
        $authors = $presenter->get('authors');
        $this->assertInstanceOf(LengthAwarePaginator::class, $authors);
        $this->assertContainsOnlyInstancesOf(AuthorPresenter::class, $authors->items());
        $this->assertSame(23, $authors->total());

        // PHP reads a public property directly: the input itself, as typed.
        $this->assertContainsOnlyInstancesOf(Author::class, $presenter->authors->items());
        $this->assertInstanceOf(Group::class, $presenter->ledger);
        $this->assertSame('12', $presenter->ledger->folio(0));
        $this->assertContainsOnlyInstancesOf(AuthorPresenter::class, $presenter->ledger->items());

        // Inside the class the inputs are still the models.
        $this->assertInstanceOf(Author::class, $presenter->rawFirst());

        // Collections, mixed ones too: only what has a presenter is wrapped.
        $this->assertInstanceOf(EditorCard::class, $presenter->desk[0]);
        $this->assertSame('Ed. Ayuk Tabe', $presenter->desk[0]->badge);
        $this->assertInstanceOf(Story::class, $presenter->desk[1]);
    }

    public function test_raw_and_present_attributes(): void
    {
        $presenter = StaffPresenter::make(authors: $this->authors());

        $this->assertInstanceOf(Author::class, $presenter->untouched);
        $this->assertContainsOnlyInstancesOf(StoryCard::class, $presenter->stories);
    }

    public function test_presenting_can_be_turned_off_except_for_marked_members(): void
    {
        config(['intermidiate.present' => false]);
        $presenter = StaffPresenter::make(authors: $this->authors());

        $this->assertInstanceOf(Author::class, $presenter->lead);
        $this->assertContainsOnlyInstancesOf(StoryCard::class, $presenter->stories);
    }

    public function test_present_finds_the_presenter_itself(): void
    {
        $presenter = StaffPresenter::make(authors: $this->authors());

        $this->assertInstanceOf(AuthorPresenter::class, $presenter->wrapOne(new Author(1, 'Ma Ngum')));
        $this->assertContainsOnlyInstancesOf(EditorCard::class, $presenter->wrapMany([new Editor(1, 'A'), null, new Editor(2, 'B')]));
        $this->assertCount(2, $presenter->wrapMany([new Editor(1, 'A'), null, new Editor(2, 'B')]));

        $this->expectException(PresenterException::class);
        $this->expectExceptionMessage('No model presenter for '.Story::class.'. Create Atannex\Intermidiate\Tests\Fixtures\Presenters\Models\StoryPresenter (php artisan make:model-presenter Story)');

        $presenter->wrapOne(new Story(1, 'x'));
    }

    public function test_the_view_and_json_get_presented_models_that_still_route_and_paginate(): void
    {
        $html = $this->get('/staff')->assertOk()->getContent();

        $this->assertStringContainsString('<p>MN Ma Ngum</p>', $html);
        $this->assertStringContainsString('<li>12 KN</li>', $html);
        $this->assertStringContainsString('<a href="http://localhost/authors/11">MN</a>', $html);

        $this->getJson('/staff')
            ->assertJsonPath('lead.initials', 'MN')
            ->assertJsonPath('authors.data.1.initials', 'KN')
            ->assertJsonPath('authors.total', 23);
    }

    public function test_a_presented_model_unwraps_into_a_parameter_typed_with_the_model(): void
    {
        $presented = AuthorPresenter::make(new Author(3, 'Ma Ngum'));

        $this->assertSame('Ma Ngum', BioPresenter::from(['author' => $presented])->name());
        $this->assertSame('<p>Ma Ngum</p>', trim(View::make('bio', BioPresenter::from(['author' => $presented])->toArray())->render()));
    }

    /** @return LengthAwarePaginator<int, Author> */
    private function authors(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([new Author(11, 'Ma Ngum'), new Author(12, 'Kola Ndi'), new Author(13, 'Eba Fon')], 23, 10, 2, ['path' => '/staff']);
    }
}

final class StaffPresenter extends Presenter
{
    /** @param  LengthAwarePaginator<int, Author>  $authors */
    public function __construct(public readonly LengthAwarePaginator $authors) {}

    public function lead(): Author
    {
        return $this->authors->items()[0];
    }

    public function ledger(): Group
    {
        return $this->partition($this->authors)->take('lead', 1)->rest('ledger')->get()->ledger;
    }

    /** @return Collection<int, object> */
    public function desk(): Collection
    {
        return new Collection([new Editor(1, 'Ayuk Tabe'), new Story(2, 'Not presented')]);
    }

    #[Raw]
    public function untouched(): Author
    {
        return $this->authors->items()[0];
    }

    /** @return list<Story> */
    #[Present(StoryCard::class)]
    public function stories(): Collection
    {
        return new Collection([new Story(1, 'One'), new Story(2, 'Two')]);
    }

    #[\Atannex\Intermidiate\Attributes\Hidden]
    public function rawFirst(): mixed
    {
        return $this->authors->items()[0];
    }

    #[\Atannex\Intermidiate\Attributes\Hidden]
    public function wrapOne(object $item): ModelPresenter
    {
        return $this->present($item);
    }

    /** @param  iterable<mixed>  $items */
    #[\Atannex\Intermidiate\Attributes\Hidden]
    public function wrapMany(iterable $items): Collection
    {
        return $this->present($items);
    }
}

final class BioPresenter extends Presenter
{
    public function __construct(private readonly Author $author) {}

    public function name(): string
    {
        return $this->author->name;
    }
}
