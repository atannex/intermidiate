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

    public function test_twitter_tags_and_any_meta_name(): void
    {
        config(['intermidiate.seo.twitter' => '@atannex']);

        $html = Meta::make()->title('Rain')->description('Wet.')->image('https://cdn.test/a.jpg')
            ->twitter(creator: '@mangum', site: '@desk')
            ->name('theme-color', '#111')
            ->toHtml();

        $this->assertStringContainsString('<meta name="twitter:title" content="Rain">', $html);
        $this->assertStringContainsString('<meta name="twitter:description" content="Wet.">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="https://cdn.test/a.jpg">', $html);
        $this->assertStringContainsString('<meta name="twitter:creator" content="@mangum">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@desk">', $html, 'set on the Meta, over config');
        $this->assertSame(1, substr_count($html, 'twitter:site'));
        $this->assertStringContainsString('<meta name="theme-color" content="#111">', $html);
    }

    public function test_a_share_title_and_card_apart_from_the_document_title(): void
    {
        $html = Meta::make()->title('Story: Top stories – ATANNEX', suffix: false)->shareTitle('Rain in Fontem')->card('player')->toHtml();

        $this->assertStringContainsString('<title>Story: Top stories – ATANNEX</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Rain in Fontem">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Rain in Fontem">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="player">', $html);
    }

    public function test_sections_fill_the_share_title_and_card(): void
    {
        $html = View::make('share')->render();

        $this->assertStringContainsString('<title>Full &amp; suffixed – Site · Laravel</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Bare headline">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="player">', $html);

        config(['app.name' => 'Site']);
        $this->assertStringContainsString('<title>Full &amp; suffixed – Site</title>', View::make('share')->render(), 'already names the site');

        config(['app.name' => 'Laravel', 'intermidiate.seo.suffix' => false]);
        $this->assertStringContainsString('<title>Full &amp; suffixed – Site</title>', View::make('share')->render());
    }

    public function test_long_titles_keep_the_site_name_and_whole_words(): void
    {
        config(['app.name' => 'ATANNEX']);
        $own = str_repeat('Ndop farmers welcome rain ', 4).'– ATANNEX';
        $this->assertSame($own, Meta::make()->title($own)->fullTitle(), 'already names the site: whole, not cut, not suffixed');

        $long = Meta::make()->title(str_repeat('Ndop farmers welcome rain ', 6))->fullTitle();
        $this->assertMatchesRegularExpression('/ (Ndop|farmers|welcome|rain)… · ATANNEX$/', $long, 'cut at a whole word, suffix kept');
        $this->assertLessThanOrEqual(95, mb_strlen($long));

        $this->assertSame('Short · ATANNEX', Meta::make()->title('Short')->fullTitle());
    }

    public function test_the_website_search_action(): void
    {
        $data = JsonLd::website('Atannex', 'https://atannex.test', 'https://atannex.test/search?q={search_term_string}')->toArray();

        $this->assertSame([
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://atannex.test/search?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ], $data['potentialAction']);
    }

    public function test_og_locale_can_be_fixed_or_left_out(): void
    {
        app()->setLocale('fr-CM');
        $this->assertStringContainsString('<meta property="og:locale" content="fr_CM">', Meta::make()->toHtml());
        $this->assertStringNotContainsString('og:locale', Meta::make()->locale(null)->toHtml());

        config(['intermidiate.seo.locale' => false]);
        $this->assertStringNotContainsString('og:locale', Meta::make()->toHtml());
        $this->assertStringContainsString('content="en_GB"', Meta::make()->locale('en-GB')->toHtml(), 'set on the Meta, over config');
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
