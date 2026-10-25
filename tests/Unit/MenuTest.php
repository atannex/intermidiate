<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Unit;

use Atannex\Intermidiate\Navigation\Item;
use Atannex\Intermidiate\Navigation\Menu;
use Atannex\Intermidiate\Navigation\Tabs;
use Atannex\Intermidiate\Tests\Fixtures\Desk;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use InvalidArgumentException;

final class MenuTest extends TestCase
{
    private function request(string $uri, ?string $route = null): Request
    {
        $request = Request::create($uri);

        if ($route !== null) {
            $request->setRouteResolver(fn () => (new Route('GET', trim(parse_url($uri, PHP_URL_PATH), '/') ?: '/', []))->name($route)->bind($request));
        }

        return $request;
    }

    private function menu(): Menu
    {
        return Menu::make()
            ->add('home', 'Home', 'http://localhost/')
            ->add('archive', 'Archive', 'http://localhost/archive', activeOn: 'archive.*')
            ->add('news', 'News', '/news');
    }

    public function test_the_exact_url_is_active(): void
    {
        $menu = $this->menu()->for($this->request('/news'));

        $this->assertSame('news', $menu->activeKey());
        $this->assertSame([false, false, true], $menu->items()->pluck('active')->all());
        $this->assertSame('page', $menu->find('news')->current());
        $this->assertNull($menu->find('home')->current());
    }

    public function test_route_patterns_and_sections(): void
    {
        $this->assertSame('archive', $this->menu()->for($this->request('/archive/2026', 'archive.year'))->activeKey());
        $this->assertNull($this->menu()->for($this->request('/news/today'))->activeKey());
        $this->assertSame('news', $this->menu()->sections()->for($this->request('/news/today'))->activeKey());
        $this->assertNull($this->menu()->sections()->for($this->request('/newsroom'))->activeKey(), 'a prefix stops at a path segment');
        $this->assertSame('home', $this->menu()->sections()->for($this->request('/'))->activeKey(), 'the home page only matches itself');
    }

    public function test_the_most_specific_match_wins(): void
    {
        $menu = Menu::make()
            ->add('desks', 'Desks', '/desks')
            ->add('sports', 'Sports', '/desks/sports')
            ->add('paths', 'Paths', null, activeOn: '/desks/*')
            ->sections()
            ->for($this->request('/desks/sports'));

        $this->assertSame('sports', $menu->activeKey());
        $this->assertSame(['sports'], $menu->items()->where('active', true)->pluck('key')->all());

        $all = Menu::make()->add('desks', 'Desks', '/desks')->add('sports', 'Sports', '/desks/sports')->sections()->multiple()->for($this->request('/desks/sports'));
        $this->assertSame(['desks', 'sports'], $all->items()->where('active', true)->pluck('key')->all());
    }

    public function test_query_strings_and_closures(): void
    {
        $menu = Menu::make()
            ->add('all', 'All', '/search')
            ->add('photos', 'Photos', '/search?type=photos')
            ->add('custom', 'Custom', null, activeOn: fn (Request $request) => $request->query('type') === 'video');

        $this->assertSame('photos', (clone $menu)->for($this->request('/search?type=photos&q=x'))->activeKey());
        $this->assertSame('all', (clone $menu)->for($this->request('/search?q=x'))->activeKey());
        $this->assertSame('custom', (clone $menu)->for($this->request('/elsewhere?type=video'))->activeKey());
    }

    public function test_children_mark_their_parents_as_the_trail(): void
    {
        $menu = Menu::make([
            ['key' => 'about', 'label' => 'About', 'url' => '/about', 'children' => [
                ['key' => 'team', 'label' => 'Team', 'url' => '/about/team'],
                ['key' => 'jobs', 'label' => 'Jobs', 'url' => '/about/jobs'],
            ]],
            ['key' => 'contact', 'label' => 'Contact', 'url' => '/contact'],
        ])->for($this->request('/about/jobs'));

        $about = $menu->find('about');
        $this->assertFalse($about->active);
        $this->assertTrue($about->trail);
        $this->assertTrue($about->open());
        $this->assertSame('jobs', $menu->active()->key);
        $this->assertSame(['about', 'jobs'], $menu->trail()->pluck('key')->all());
        $this->assertSame('nav is-open', $about->classes('nav', 'is-active', trail: 'is-open'));
    }

    public function test_forcing_a_key(): void
    {
        $menu = $this->menu()->force('archive')->for($this->request('/news'));

        $this->assertSame('archive', $menu->activeKey());

        $this->expectException(InvalidArgumentException::class);
        $this->menu()->force('nope')->items();
    }

    public function test_classes_and_attributes(): void
    {
        $item = $this->menu()->for($this->request('/news'))->find('news');

        $this->assertSame('link is-active', $item->classes('link'));
        $this->assertSame('link', $this->menu()->for($this->request('/news'))->find('home')->classes('link'));
        $this->assertSame('href="/news" class="link is-active" aria-current="page"', (string) $item->attributes(['class' => 'link']));
        $this->assertSame('href="http://localhost/archive"', (string) $this->menu()->for($this->request('/news'))->find('archive')->attributes());
    }

    public function test_from_rows_and_items(): void
    {
        $menu = Menu::from(['news' => 'News', 'sports' => 'Sports'], fn (string $label, string $key) => Item::make($key, $label, "/{$key}")->badge(3)->meta(['icon' => 'x']))
            ->for($this->request('/sports'));

        $this->assertSame('sports', $menu->activeKey());
        $this->assertSame(3, $menu->find('news')->badge);
        $this->assertSame('x', $menu->find('news')->get('icon'));
        $this->assertCount(2, $menu);
        $this->assertSame(['key' => 'news', 'label' => 'News', 'url' => '/news', 'badge' => 3, 'meta' => ['icon' => 'x'], 'active' => false, 'trail' => false], $menu->toArray()[0]);
    }

    public function test_the_current_request_is_used_by_default(): void
    {
        $this->app->instance('request', $this->request('/news'));

        $this->assertSame('news', $this->menu()->activeKey());
    }

    public function test_tabs_follow_a_query_parameter(): void
    {
        $tabs = Tabs::query('sort', ['latest' => 'Latest', 'popular' => 'Most read'], request: $this->request('/desk?sort=popular&page=3&q=a'));

        $this->assertSame('popular', $tabs->selected());
        $this->assertSame('Most read', $tabs->label());
        $this->assertFalse($tabs->isDefault());
        $this->assertTrue($tabs->is('popular'));
        $this->assertSame('popular', $tabs->menu()->activeKey());
        $this->assertSame('http://localhost/desk?q=a', $tabs->items()[0]->url, 'the default drops its parameter and ?page');
        $this->assertSame('http://localhost/desk?sort=popular&q=a', $tabs->items()[1]->url);
    }

    public function test_tabs_reject_unknown_values_and_accept_enums(): void
    {
        $tabs = Tabs::query('sort', ['latest', 'popular'], request: $this->request('/desk?sort=<script>'));
        $this->assertSame('latest', $tabs->selected());
        $this->assertSame('Popular', $tabs->items()[1]->label);

        $desks = Tabs::query('desk', Desk::class, Desk::Sports, $this->request('/?desk=economy'));
        $this->assertSame(Desk::Economy, $desks->selected());
        $this->assertSame('Sports desk', $desks->items()[1]->label);
        $this->assertSame(['parameter' => 'desk', 'selected' => 'economy'], array_slice($desks->jsonSerialize(), 0, 2));
    }
}
