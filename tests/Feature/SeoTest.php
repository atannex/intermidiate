<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Navigation\Menu;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Seo\JsonLd;
use Atannex\Intermidiate\Seo\Meta;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

final class SeoTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/story', fn () => StoryPagePresenter::make()->view('meta'));
        Route::get('/guess', fn () => view('meta', ['title' => 'Guessed & found', 'image' => '/img/a.jpg']));
    }

    public function test_meta_renders_every_tag_once_escaped(): void
    {
        config(['app.name' => 'Atannex', 'intermidiate.seo.twitter' => '@atannex']);

        $html = Meta::make()
            ->article('2026-03-07 08:00:00', author: 'Ma Ngum', section: 'Weather')
            ->title('Rain & "wind" in Fontem')
            ->description('<p>Heavy rain closed the road to Fontem on Saturday.</p>')
            ->image('/img/rain.jpg')
            ->canonical('https://atannex.test/stories/rain')
            ->breadcrumbs(['Home' => '/', 'Weather' => '/weather'])
            ->toHtml();

        $this->assertStringContainsString('<title>Rain &amp; &quot;wind&quot; in Fontem · Atannex</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Heavy rain closed the road to Fontem on Saturday.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://atannex.test/stories/rain">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="http://localhost/img/rain.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="article:section" content="Weather">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@atannex">', $html);
        $this->assertStringContainsString('"@type":"NewsArticle","headline":"Rain \\u0026 \\u0022wind\\u0022 in Fontem"', $html, 'built after the title, whatever the call order');
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('</script><', str_replace('</script>'."\n", '', $html));
    }

    public function test_json_ld_is_safe_inside_a_script_tag(): void
    {
        $html = JsonLd::make('Thing', ['name' => '</script><script>alert(1)</script>'])->toHtml();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function test_breadcrumbs_from_a_menu_trail(): void
    {
        $data = JsonLd::breadcrumbs([...Menu::make()->add('home', 'Home', '/')->add('news', 'News', '/news')])->toArray();

        $this->assertSame(['@type' => 'ListItem', 'position' => 2, 'name' => 'News', 'item' => '/news'], $data['itemListElement'][1]);
    }

    public function test_the_meta_directive_uses_the_presenters_meta_member(): void
    {
        $this->get('/story')->assertSee('<title>From the member · Laravel</title>', false);
    }

    public function test_the_meta_directive_guesses_from_variables_and_sections(): void
    {
        $this->get('/guess?page=2&utm=x')
            ->assertSee('<title>Guessed &amp; found · Laravel</title>', false)
            ->assertSee('<meta name="description" content="From the section &amp; more">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/guess?page=2">', false)
            ->assertSee('<meta property="og:image" content="http://localhost/img/a.jpg">', false);

        $this->assertStringContainsString('<title>Laravel</title>', View::make('meta')->render(), 'no title: the site name');
    }
}

final class StoryPagePresenter extends Presenter
{
    public function meta(): Meta
    {
        return Meta::make()->title('From the member');
    }
}
