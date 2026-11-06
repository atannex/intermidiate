<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Tests\Fixtures\Livewire\ArticleCard;
use Atannex\Intermidiate\Tests\Fixtures\Livewire\ArticleState;
use Atannex\Intermidiate\Tests\Fixtures\Livewire\EloquentCard;
use Atannex\Intermidiate\Tests\Fixtures\Livewire\LivewireArticlePresenter;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Atannex\Intermidiate\Tests\Fixtures\Models\CreatesArticles;
use Atannex\Intermidiate\Tests\Fixtures\Models\Writer;
use Atannex\Intermidiate\Tests\TestCase;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

/**
 * Livewire is a development dependency only: these run when it's installed
 * and show the two ways to use model presenters in a component: in
 * render(), or their serialized (declared-fields) form as state.
 */
final class LivewireTest extends TestCase
{
    use CreatesArticles;

    protected function setUp(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->markTestSkipped('livewire/livewire is not installed.');
        }

        require_once __DIR__.'/../Fixtures/Livewire/components.php';

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), LivewireServiceProvider::class];
    }

    public function test_a_component_presents_in_render(): void
    {
        Livewire::test(ArticleCard::class, ['articleId' => 7])
            ->assertSee('Rain in Fontem')
            ->assertDontSee('secret body')
            ->call('rename')
            ->assertSee('Rain in Buea');
    }

    public function test_a_component_keeps_the_array_form_as_state(): void
    {
        Livewire::test(ArticleState::class)
            ->assertSet('article', ['id' => 7, 'headline' => 'Rain in Fontem'])
            ->assertSee('Rain in Fontem');
    }

    public function test_a_component_presents_an_eloquent_model_across_requests(): void
    {
        $this->createArticles();
        Writer::create(['name' => 'Ma Ngum'])->articles()->create(['title' => 'Rain', 'body' => 'one two three']);
        config(['intermidiate.models' => [Article::class => LivewireArticlePresenter::class]]);
        $this->app->forgetInstance(Intermidiate::class);

        Livewire::test(EloquentCard::class, ['articleId' => 1])
            ->assertSee('Rain · By Ma Ngum')
            ->assertDontSee('words')
            ->call('expand')
            ->assertSee('Rain · By Ma Ngum · 3 words');
    }
}
