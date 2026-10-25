<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Attributes\Expose;
use Atannex\Intermidiate\Exceptions\InvalidViewData;
use Atannex\Intermidiate\Exceptions\MissingViewData;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Exceptions\UnknownMember;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Slicing\Group;
use Atannex\Intermidiate\Support\Deferred;
use Atannex\Intermidiate\Support\Schema;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\ArchivePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Presenters\DeskPresenter;
use Atannex\Intermidiate\Tests\TestCase;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;

final class PresenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DeskPresenter::$prepared = DeskPresenter::$computed = ArchivePresenter::$related = 0;
    }

    public function test_public_members_become_view_variables(): void
    {
        $data = DeskPresenter::make(desk: Desk::News, page: 2)->toArray();

        $this->assertSame('Hello', $data['greeting']);
        $this->assertSame(Desk::News, $data['desk']);
        $this->assertSame(2, $data['page']);
        $this->assertSame('News desk', $data['title']);
        $this->assertSame('Page 2', $data['pageLabel'], '#[Expose] renames');
        $this->assertInstanceOf(Closure::class, $data['heading']);
        $this->assertSame('News desk today', $data['heading']('today'));
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('internal', $data);
        $this->assertArrayNotHasKey('label', $data);
        $this->assertArrayNotHasKey('prepare', $data);
        $this->assertArrayNotHasKey('toArray', $data);
        $this->assertInstanceOf(DeskPresenter::class, $data['presenter']);
    }

    public function test_methods_run_once_and_prepare_runs_first(): void
    {
        $presenter = DeskPresenter::make(desk: Desk::News);

        $presenter->toArray();
        $presenter->toArray();
        $this->assertSame('News desk', $presenter->title);
        $this->assertSame('News desk', $presenter['title']);
        $presenter->heading('x');

        $this->assertSame(1, DeskPresenter::$computed);
        $this->assertSame(1, DeskPresenter::$prepared);
    }

    public function test_members_read_in_snake_case_and_explain_typos(): void
    {
        $presenter = DeskPresenter::make(desk: Desk::News);

        $this->assertSame('markup', $presenter->view_only);
        $this->assertTrue($presenter->has('pageLabel'));
        $this->assertTrue(isset($presenter->title));

        $this->expectException(UnknownMember::class);
        $this->expectExceptionMessage('has no "titel". Did you mean "title"?');
        $presenter->titel;
    }

    public function test_it_is_read_only(): void
    {
        $this->expectException(PresenterException::class);

        $presenter = DeskPresenter::make(desk: Desk::News);
        $presenter['title'] = 'x';
    }

    public function test_only_except_and_with(): void
    {
        $presenter = DeskPresenter::make(desk: Desk::Sports)->with('extra', 1)->with(['more' => 2]);

        $this->assertSame(['title' => 'Sports desk', 'extra' => 1], $presenter->only('title', 'extra'));
        $this->assertArrayNotHasKey('title', $presenter->except('title'));
        $this->assertSame(2, $presenter->more);
    }

    public function test_json_leaves_out_closures_and_view_only_members(): void
    {
        $json = DeskPresenter::make(desk: Desk::News)->with('fn', fn () => 1)->jsonSerialize();

        $this->assertSame('News desk', $json['title']);
        $this->assertArrayNotHasKey('heading', $json);
        $this->assertArrayNotHasKey('viewOnly', $json);
        $this->assertArrayNotHasKey('fn', $json);
        $this->assertArrayNotHasKey('presenter', $json);
    }

    public function test_lazy_members_wait_until_used(): void
    {
        $presenter = ArchivePresenter::make(posts: $this->stories(5), year: 2026);
        $related = $presenter->toArray()['related'];

        $this->assertInstanceOf(Deferred::class, $related);
        $this->assertSame(0, ArchivePresenter::$related);
        $this->assertCount(2, $related);
        $this->assertSame(['one', 'two'], iterator_to_array($related));
        $this->assertSame('one', $related->first());
        $this->assertSame(1, ArchivePresenter::$related);
    }

    public function test_make_takes_named_or_positional_values_and_checks_names(): void
    {
        $this->assertSame(Desk::Economy, DeskPresenter::make(Desk::Economy, 4)->desk);
        $this->assertSame(4, DeskPresenter::make(Desk::Economy, 4)->page);

        $this->expectException(PresenterException::class);
        $this->expectExceptionMessage('has no constructor parameter $paeg. Did you mean "page"?');
        DeskPresenter::make(desk: Desk::News, paeg: 2);
    }

    public function test_strings_are_coerced_to_the_declared_types(): void
    {
        $presenter = DeskPresenter::from(['desk' => 'sports', 'page' => '3', 'since' => '2026-03-01', 'compact' => 'true', 'ignored' => 'x']);

        $this->assertSame(Desk::Sports, $presenter->desk);
        $this->assertSame(3, $presenter->page);
        $this->assertInstanceOf(CarbonImmutable::class, $presenter->since);
        $this->assertSame('2026-03-01', $presenter->since->toDateString());
        $this->assertTrue($presenter->compact);
        $this->assertInstanceOf(Request::class, $presenter->request, 'class types come from the container');
    }

    public function test_loose_data_matches_snake_case_keys(): void
    {
        $presenter = ArchivePresenter::from(['posts' => $this->stories(3), 'year' => '2026', 'months_with_posts' => ['01']]);

        $this->assertTrue($presenter->calendar[0]->filled);
        $this->assertFalse($presenter->calendar[1]->filled);
    }

    public function test_bad_values_name_the_parameter(): void
    {
        $this->expectException(InvalidViewData::class);
        $this->expectExceptionMessage('DeskPresenter expects $page to be int, got string "two".');

        DeskPresenter::from(['desk' => 'news', 'page' => 'two']);
    }

    public function test_bad_enum_values_are_rejected(): void
    {
        $this->expectException(InvalidViewData::class);
        $this->expectExceptionMessage('expects $desk to be '.Desk::class.', got string "weather"');

        DeskPresenter::from(['desk' => 'weather']);
    }

    public function test_coercion_can_be_turned_off(): void
    {
        config(['intermidiate.coerce' => false]);

        $this->expectException(InvalidViewData::class);
        DeskPresenter::from(['desk' => Desk::News, 'page' => '3']);
    }

    public function test_missing_data_lists_what_there_is(): void
    {
        $this->expectException(MissingViewData::class);
        $this->expectExceptionMessage('ArchivePresenter needs $posts (Illuminate\Pagination\LengthAwarePaginator), but the data has no "posts". Did you mean "post"? Available: $post, $year.');

        ArchivePresenter::from(['post' => 1, 'year' => 2026]);
    }

    public function test_the_archive_layout(): void
    {
        $presenter = ArchivePresenter::make(posts: $this->stories(25, page: 2), year: 2026, month: 3, monthsWithPosts: ['03']);

        $this->assertSame(11, $presenter->lead->id);
        $this->assertInstanceOf(Group::class, $presenter->beside);
        $this->assertSame(['12', '13'], $presenter->beside->entries()->pluck('folio')->all());
        $this->assertSame('14', $presenter->ledger->folio(0));
        $this->assertSame('March 2026', $presenter->period);
        $this->assertTrue($presenter->calendar[2]->current);
        $this->assertSame('/archive/2026/03', $presenter->calendar[2]->url);
        $this->assertNull($presenter->calendar[3]->url);
        $this->assertSame('02 / 03', $presenter->pages->fraction());
        $this->assertSame('archive', $presenter->template());
    }

    public function test_schema_describes_members(): void
    {
        $schema = Schema::of(DeskPresenter::class);

        $this->assertSame(['greeting', 'desk', 'page', 'since', 'compact', 'request', 'title', 'pageLabel', 'heading', 'viewOnly'], $schema->keys());
        $this->assertSame('Closure → string', $schema->member('heading')->describe());
        $this->assertSame('string (view only)', $schema->member('viewOnly')->describe());
        $this->assertSame('Illuminate\Support\Collection (lazy)', Schema::of(ArchivePresenter::class)->member('related')->describe());
    }

    public function test_duplicate_keys_are_refused(): void
    {
        $this->expectException(PresenterException::class);
        $this->expectExceptionMessage('exports "title" twice');

        Schema::of(Duplicate::class);
    }

    public function test_a_view_model_without_a_view_says_so(): void
    {
        $this->expectExceptionMessage('has no view');

        DeskPresenter::make(desk: Desk::News)->toView();
    }
}

final class Duplicate extends Presenter
{
    public string $title = 'a';

    #[Expose('title')]
    public function headline(): string
    {
        return 'b';
    }
}
