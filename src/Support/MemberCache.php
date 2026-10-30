<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Cached;
use Illuminate\Container\Container;

/**
 * @internal Backs #[Cached]: keeps a member's value in Laravel's cache. The
 * key holds the class, the member, the locale, the presenter's inputs (a
 * model presenter's model), its cacheScope() (tenant and other context the
 * inputs don't carry) and, unless the member is marked shared, the
 * signed-in user of the default guard, so one user's value is never served
 * to another. When an input has no reliable identity (a closure, an unsaved
 * model…), the member isn't cached at all.
 */
final class MemberCache
{
    public static function remember(object $presenter, string $method, Cached $cached): mixed
    {
        $container = Container::getInstance();

        if (! $container->bound('cache')) {
            return $presenter->{$method}();
        }

        $cache = $container->make('cache')->store($cached->store);

        if ($cached->tags !== [] && method_exists($cache->getStore(), 'tags')) {
            $cache = $cache->tags($cached->tags);
        }

        $inputs = Fingerprint::of($presenter);
        $scope = Fingerprint::hash((fn (): array => $this->cacheScope())->call($presenter));

        // No reliable identity for an input or the scope: computed fresh rather than risk serving another's value.
        if ($inputs === null || $scope === null) {
            return $presenter->{$method}();
        }

        $key = sprintf('intermidiate:%s:%s:%s:%s:%s:%s', $presenter::class, $method, Settings::locale(), self::audience($cached), $inputs, $scope);
        $ttl = $cached->ttl();
        $compute = fn () => $presenter->{$method}();

        return $ttl === null ? $cache->rememberForever($key, $compute) : $cache->remember($key, $ttl, $compute);
    }

    private static function audience(Cached $cached): string
    {
        $container = Container::getInstance();

        if ($cached->shared || ! $container->bound('auth')) {
            return 'shared';
        }

        $id = $container->make('auth')->guard()->id();

        return $id === null ? 'guest' : 'user-'.md5((string) $id);
    }
}
