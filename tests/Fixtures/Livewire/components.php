<?php

declare(strict_types=1);

// Loaded by LivewireTest only when Livewire is installed: these classes extend its Component.

namespace Atannex\Intermidiate\Tests\Fixtures\Livewire;

use Atannex\Intermidiate\Facades\Intermidiate;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Models\Article;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

final class ArticleCard extends Component
{
    public int $articleId;

    public string $title = 'Rain in Fontem';

    public function rename(): void
    {
        $this->title = 'Rain in Buea';
    }

    public function render(): string
    {
        $article = CardPresenter::make(['id' => $this->articleId, 'title' => $this->title, 'body' => 'secret body']);

        return Blade::render('<div>{{ $article->headline }}</div>', ['article' => $article]);
    }
}

final class ArticleState extends Component
{
    /** @var array<string, mixed> */
    public array $article = [];

    public function mount(): void
    {
        $this->article = CardPresenter::make(['id' => 7, 'title' => 'Rain in Fontem', 'body' => 'secret body'])->toArray();
    }

    public function render(): string
    {
        return '<div>{{ $article["headline"] }}</div>';
    }
}

/** @extends ModelPresenter<array<string, mixed>> */
final class CardPresenter extends ModelPresenter
{
    protected array $visible = ['id', 'headline'];

    public function headline(): string
    {
        return (string) $this->subject['title'];
    }
}

final class EloquentCard extends Component
{
    public int $articleId;

    public bool $long = false;

    public function expand(): void
    {
        $this->long = true;
    }

    public function render(): string
    {
        // Load in render(), wrap explicitly: the component keeps only the id as state.
        $article = Intermidiate::wrap(Article::with('writer')->findOrFail($this->articleId));

        return Blade::render('<div>{{ $article->title }} · {{ $article->byline }}@if ($long) · {{ $article->words }} words @endif</div>', ['article' => $article, 'long' => $this->long]);
    }
}

/** @extends ModelPresenter<Article> */
final class LivewireArticlePresenter extends ModelPresenter
{
    public function byline(): string
    {
        return 'By '.$this->subject->writer->name;
    }

    public function words(): int
    {
        return str_word_count($this->subject->body);
    }
}
