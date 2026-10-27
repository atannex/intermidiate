<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Navigation;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Decides how well an item matches the current request. Higher is more
 * specific; 0 is no match. An exact URL beats a route or path pattern,
 * which beats a section prefix ("/archive" while on "/archive/2026").
 *
 * Patterns: "archive.*" (route name), "/archive/*" or "path:archive/*"
 * (path), "url:https://…" (full URL), or a Closure(Request, Item): bool.
 */
final class Active
{
    /** @var array{string, string, string|false|null}|null path, host, route name (false: no route), read on the first score */
    private ?array $current = null;

    public function __construct(private readonly Request $request, private readonly bool $sections = false) {}

    public function score(Item $item): int
    {
        $score = 0;

        foreach ($item->patterns as $pattern) {
            $score = max($score, $this->pattern($pattern, $item));
        }

        if ($item->url !== null) {
            $score = max($score, $this->url($item->url));
        }

        return $score;
    }

    private function pattern(string|Closure $pattern, Item $item): int
    {
        if ($pattern instanceof Closure) {
            return $pattern($this->request, $item) ? 2000 : 0;
        }

        $literal = strlen(str_replace('*', '', $pattern));

        if (str_starts_with($pattern, 'url:')) {
            return rtrim(substr($pattern, 4), '/') === rtrim($this->request->url(), '/') ? 2500 + $literal : 0;
        }

        if (str_starts_with($pattern, 'path:') || str_contains($pattern, '/')) {
            $path = trim((string) preg_replace('/^path:/', '', $pattern), '/');

            return Str::is($path === '' ? '/' : $path, $this->path()) ? 2000 + $literal : 0;
        }

        $name = str_starts_with($pattern, 'route:') ? substr($pattern, 6) : $pattern;
        $route = $this->current()[2];

        // As routeIs(), with the route's name read once for the whole menu.
        return is_string($route) && Str::is($name, $route) ? 2000 + $literal : 0;
    }

    private function url(string $url): int
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return 0;
        }

        if (isset($parts['host']) && strcasecmp($parts['host'], $this->current()[1]) !== 0) {
            return 0;
        }

        $path = trim($parts['path'] ?? '', '/');
        $path = $path === '' ? '/' : $path;
        $current = $this->path();

        parse_str($parts['query'] ?? '', $query);

        foreach ($query as $key => $value) {
            if ((string) $this->request->query($key) !== (string) (is_array($value) ? '' : $value)) {
                return 0;
            }
        }

        if ($path === $current) {
            return 3000 + strlen($path) + 100 * count($query);
        }

        if ($this->sections && $path !== '/' && str_starts_with($current.'/', $path.'/')) {
            return 1000 + strlen($path);
        }

        return 0;
    }

    private function path(): string
    {
        return $this->current()[0];
    }

    /**
     * The request's path, host and route name, the same for every item:
     * getHost() validates the host and route() runs the resolver each call.
     *
     * @return array{string, string, string|false|null}
     */
    private function current(): array
    {
        if ($this->current === null) {
            $path = trim($this->request->path(), '/');
            $route = $this->request->route();

            $this->current = [$path === '' ? '/' : $path, $this->request->getHost(), $route === null ? false : $route->getName()];
        }

        return $this->current;
    }
}
