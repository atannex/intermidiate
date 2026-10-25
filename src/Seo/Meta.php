<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Seo;

use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Support\Format;
use Atannex\Intermidiate\Support\Settings;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Stringable;

/**
 * Everything in a page's <head> that search engines and share cards read:
 * <title>, description, canonical, robots, Open Graph, Twitter and JSON-LD.
 * Text is stripped, decoded, trimmed to length and escaped once.
 *
 *     public function meta(): Meta
 *     {
 *         return Meta::make()->title($this->post->title)->description($this->post->body)
 *             ->image($this->post->image_url)->article($this->post->published_at, author: $this->post->author->name);
 *     }
 *
 * In the layout's <head>, `@meta` prints it. A page without a meta() member
 * still gets one, guessed from its variables ($title, $description,
 * $image…) and sections (@section('title'), @section('meta:description')).
 */
final class Meta implements Htmlable, Stringable
{
    /** Variable names, then section names, read when guessing. */
    private const GUESS = [
        'title' => [['metaTitle', 'seoTitle', 'pageTitle', 'title', 'headline'], ['og:title', 'title']],
        'description' => [['metaDescription', 'seoDescription', 'description', 'excerpt', 'summary'], ['meta:description', 'description']],
        'image' => [['ogImage', 'metaImage', 'image', 'imageUrl', 'cover'], ['og:image', 'image']],
        'canonical' => [['canonical', 'canonicalUrl'], ['canonical']],
        'robots' => [['robots'], ['robots']],
    ];

    private ?string $title = null;

    private ?string $description = null;

    private ?string $canonical = null;

    private ?string $image = null;

    private string $type = 'website';

    private ?string $robots = null;

    private bool $suffix = true;

    /** @var array<string, string> */
    private array $properties = [];

    /** @var list<JsonLd> */
    private array $schemas = [];

    /** @var array{DateTimeInterface|string|null, DateTimeInterface|string|null, string|null}|null built when rendered, so the order of calls doesn't matter */
    private ?array $article = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * What a view's variables say about the page: its $meta, its
     * Presenter's meta() member, or a guess from its variables and sections.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function forView(array $variables): self
    {
        foreach ($variables as $value) {
            if ($value instanceof self) {
                return $value;
            }
        }

        $presenter = null;

        foreach ($variables as $value) {
            if ($value instanceof Presenter) {
                $presenter = $value;

                if ($value->has('meta') && ($meta = $value->get('meta')) instanceof self) {
                    return $meta;
                }
            }
        }

        $meta = new self;
        $factory = $variables['__env'] ?? null;

        foreach (self::GUESS as $field => [$names, $sections]) {
            $value = self::guess($variables, $presenter, $names) ?? self::section($factory, $sections);

            if ($value !== null) {
                $meta->{$field}($value);
            }
        }

        return $meta;
    }

    public function title(?string $title, bool $suffix = true): self
    {
        $this->title = $title === null ? null : Format::text(self::decode($title));
        $this->suffix = $suffix;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description === null ? null : Format::excerpt(self::decode($description), (int) Settings::get('seo.description_limit', 160));

        return $this;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url): self
    {
        $this->image = $url === null || $url === '' ? null : self::absolute($url);

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function robots(?string $robots): self
    {
        $this->robots = $robots;

        return $this;
    }

    /** Keep the page out of search results (filters, searches, drafts). */
    public function noindex(bool $follow = true): self
    {
        return $this->robots($follow ? 'noindex, follow' : 'noindex, nofollow');
    }

    /** An article: og:type, publish and update times, author and section. */
    public function article(
        DateTimeInterface|string|null $published = null,
        DateTimeInterface|string|null $modified = null,
        ?string $author = null,
        ?string $section = null,
        bool $schema = true,
    ): self {
        $this->type = 'article';
        $this->property('article:published_time', Format::iso($published));
        $this->property('article:modified_time', Format::iso($modified ?? $published));
        $this->property('article:author', $author);
        $this->property('article:section', $section);

        $this->article = $schema ? [$published, $modified, $author] : null;

        return $this;
    }

    /** Any other <meta property> (og:*, article:*, fb:*…). */
    public function property(string $property, ?string $content): self
    {
        if ($content !== null && $content !== '') {
            $this->properties[$property] = $content;
        }

        return $this;
    }

    public function schema(JsonLd ...$schemas): self
    {
        array_push($this->schemas, ...$schemas);

        return $this;
    }

    /** @param  iterable<mixed>  $trail  a menu's trail(), or label => url */
    public function breadcrumbs(iterable $trail): self
    {
        return $this->schema(JsonLd::breadcrumbs($trail));
    }

    /** "Rain in Fontem · Atannex", or the site's name alone. */
    public function fullTitle(): string
    {
        $site = self::site();

        if ($this->title === null || $this->title === '') {
            return $site;
        }

        $title = mb_substr($this->title, 0, (int) Settings::get('seo.title_limit', 95));

        return $this->suffix && $site !== '' && $title !== $site ? $title.Settings::get('seo.separator', ' · ').$site : $title;
    }

    /** The canonical URL: as set, or this page without its query string (page 2+ keeps ?page). */
    public function url(): ?string
    {
        if ($this->canonical !== null) {
            return self::absolute($this->canonical);
        }

        $request = self::request();

        if ($request === null) {
            return null;
        }

        $page = (int) $request->query('page', 1);

        return $request->url().($page > 1 ? '?page='.$page : '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->fullTitle(),
            'description' => $this->description,
            'canonical' => $this->url(),
            'image' => $this->image,
            'type' => $this->type,
            'robots' => $this->robots,
            'properties' => $this->properties,
            'schemas' => array_map(fn (JsonLd $schema) => $schema->toArray(), $this->schemas()),
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function toHtml(): string
    {
        $url = $this->url();
        $title = $this->fullTitle();
        $site = self::site();

        $tags = ['<title>'.e($title).'</title>'];
        $tags[] = self::tag('name', 'description', $this->description);
        $tags[] = self::tag('name', 'robots', $this->robots);
        $tags[] = $url === null ? null : '<link rel="canonical" href="'.e($url).'">';

        foreach ([
            'og:type' => $this->type,
            'og:site_name' => $site,
            'og:title' => $this->title ?? $site,
            'og:description' => $this->description,
            'og:url' => $url,
            'og:image' => $this->image,
            'og:locale' => str_replace('-', '_', Settings::locale()),
            ...$this->properties,
        ] as $property => $content) {
            $tags[] = self::tag('property', $property, $content);
        }

        $tags[] = self::tag('name', 'twitter:card', $this->image === null ? 'summary' : 'summary_large_image');
        $tags[] = self::tag('name', 'twitter:site', Settings::get('seo.twitter'));

        foreach ($this->schemas() as $schema) {
            $tags[] = $schema->toHtml();
        }

        return implode("\n", array_filter($tags));
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /** @return list<JsonLd> */
    private function schemas(): array
    {
        if ($this->article === null || $this->title === null) {
            return $this->schemas;
        }

        [$published, $modified, $author] = $this->article;

        return [JsonLd::article($this->title, $this->url(), $this->image, $published, $modified, $author, self::site(), $this->description), ...$this->schemas];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  list<string>  $names
     */
    private static function guess(array $variables, ?Presenter $presenter, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $variables[$name] ?? ($presenter?->has($name) ? $presenter->get($name) : null);

            if (is_string($value) && trim($value) !== '' || $value instanceof Stringable && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /** @param  list<string>  $sections */
    private static function section(mixed $factory, array $sections): ?string
    {
        if (! is_object($factory) || ! method_exists($factory, 'hasSection')) {
            return null;
        }

        foreach ($sections as $section) {
            if ($factory->hasSection($section) && trim($value = (string) $factory->yieldContent($section)) !== '') {
                return $value;
            }
        }

        return null;
    }

    /** Section values arrive escaped once already; decode so the tags escape exactly once. */
    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function tag(string $attribute, string $name, mixed $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        return '<meta '.$attribute.'="'.e($name).'" content="'.e((string) $content).'">';
    }

    private static function site(): string
    {
        $site = Settings::get('seo.site');

        if (is_string($site)) {
            return $site;
        }

        $container = Container::getInstance();

        return $container->bound('config') ? (string) $container->make('config')->get('app.name', '') : '';
    }

    private static function absolute(string $url): string
    {
        $container = Container::getInstance();

        return preg_match('#^(https?:)?//#i', $url) || ! $container->bound('url') ? $url : $container->make('url')->to($url);
    }

    private static function request(): ?Request
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;

        return $request instanceof Request ? $request : null;
    }
}
