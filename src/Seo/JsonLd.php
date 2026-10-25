<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Seo;

use Atannex\Intermidiate\Navigation\Item;
use Atannex\Intermidiate\Support\Format;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use Stringable;

/**
 * schema.org structured data, encoded so it's safe inside a <script> tag:
 *
 *     JsonLd::article($post->title, url: $url, image: $image, published: $post->published_at, author: $post->author->name)
 *     JsonLd::breadcrumbs($menu->trail())          // or ['Home' => '/', 'Archive' => '/archive']
 *     JsonLd::make('Event', ['name' => …])
 *
 * @implements Arrayable<string, mixed>
 */
final class JsonLd implements Arrayable, Htmlable, JsonSerializable, Stringable
{
    public const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /** @param  array<string, mixed>  $data */
    private function __construct(private readonly array $data) {}

    /** @param  array<string, mixed>  $data */
    public static function make(string $type, array $data = []): self
    {
        return new self(['@context' => 'https://schema.org', '@type' => $type, ...self::clean($data)]);
    }

    public static function article(
        string $headline,
        ?string $url = null,
        string|array|null $image = null,
        DateTimeInterface|string|null $published = null,
        DateTimeInterface|string|null $modified = null,
        ?string $author = null,
        ?string $publisher = null,
        ?string $description = null,
        string $type = 'NewsArticle',
    ): self {
        return self::make($type, [
            'headline' => mb_substr(Format::text($headline), 0, 110),
            'description' => $description === null ? null : Format::excerpt($description),
            'mainEntityOfPage' => $url,
            'url' => $url,
            'image' => $image,
            'datePublished' => Format::iso($published),
            'dateModified' => Format::iso($modified ?? $published),
            'author' => $author === null ? null : ['@type' => 'Person', 'name' => $author],
            'publisher' => $publisher === null ? null : ['@type' => 'Organization', 'name' => $publisher],
        ]);
    }

    /**
     * @param  iterable<mixed>  $trail  Items (a menu's trail), [label, url] pairs, ['label' => …, 'url' => …] rows, or label => url
     */
    public static function breadcrumbs(iterable $trail): self
    {
        $items = [];
        $position = 1;

        foreach ($trail as $key => $crumb) {
            [$label, $url] = match (true) {
                $crumb instanceof Item => [$crumb->label, $crumb->url],
                is_array($crumb) && array_is_list($crumb) => [$crumb[0] ?? '', $crumb[1] ?? null],
                is_array($crumb) => [$crumb['label'] ?? $crumb['name'] ?? '', $crumb['url'] ?? null],
                default => [(string) $key, is_string($crumb) ? $crumb : null],
            };

            $items[] = array_filter(['@type' => 'ListItem', 'position' => $position++, 'name' => Format::text((string) $label), 'item' => $url], fn ($value) => $value !== null);
        }

        return self::make('BreadcrumbList', ['itemListElement' => $items]);
    }

    public static function organization(string $name, ?string $url = null, ?string $logo = null, array $sameAs = []): self
    {
        return self::make('Organization', ['name' => $name, 'url' => $url, 'logo' => $logo, 'sameAs' => $sameAs ?: null]);
    }

    /** @param  string|null  $search  a search URL with {search_term_string}, for the sitelinks search box */
    public static function website(string $name, ?string $url = null, ?string $search = null): self
    {
        return self::make('WebSite', [
            'name' => $name,
            'url' => $url,
            'potentialAction' => $search === null ? null : ['@type' => 'SearchAction', 'target' => $search, 'query-input' => 'required name=search_term_string'],
        ]);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function encode(): string
    {
        return (string) json_encode($this->data, self::FLAGS);
    }

    public function toHtml(): string
    {
        return '<script type="application/ld+json">'.$this->encode().'</script>';
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
