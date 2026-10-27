<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Config;
use Atannex\Intermidiate\Attributes\CurrentUser;
use Atannex\Intermidiate\Attributes\Old;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * @internal Values a Presenter would otherwise read with config(), auth(),
 * old(), session() or app()->getLocale(): by attribute, or for a few
 * well-known names with no attribute at all.
 */
final class Ambient
{
    /** Parameter names that mean "the signed-in user" when typed with a user class. */
    private const USER_NAMES = ['user', 'viewer', 'currentUser', 'me', 'authUser'];

    /**
     * Marked parameters: #[Config], #[Old], #[Session], #[CurrentUser].
     *
     * @return array{bool, mixed}
     */
    public static function marked(Parameter $parameter): array
    {
        $attribute = $parameter->marker;

        if ($attribute === null) {
            return [false, null];
        }

        if ($attribute instanceof Config) {
            $container = Container::getInstance();
            $config = $container->bound('config') ? $container->make('config') : null;

            return $config !== null && $config->has($attribute->key) ? [true, $config->get($attribute->key)] : [false, null];
        }

        if ($attribute instanceof CurrentUser) {
            return [true, self::user($parameter, $attribute->guard)];
        }

        $request = self::request();

        if (! $request?->hasSession()) {
            return [false, null];
        }

        $value = $attribute instanceof Old
            ? self::first($parameter, $attribute->key, fn (string $key) => $request->old($key))
            : self::first($parameter, $attribute->key, fn (string $key) => $request->session()->get($key));

        return $value === null ? [false, null] : [true, $value];
    }

    /**
     * What an unmarked parameter is known as by its name: a user-typed $user
     * (or $viewer…), string $locale and string $timezone. Only when the
     * parameter has no default: `?User $user = null` or `?string $timezone = null`
     * keeps its own meaning (an author, a fallback of its own).
     *
     * @return 'user'|'locale'|'timezone'|null
     */
    public static function kind(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        $name = $parameter->getName();

        if (! $type instanceof ReflectionNamedType || $parameter->isDefaultValueAvailable()) {
            return null;
        }

        if (! $type->isBuiltin() && in_array($name, self::USER_NAMES, true) && is_a($type->getName(), Authenticatable::class, true)) {
            return 'user';
        }

        return $type->getName() === 'string' && in_array($name, ['locale', 'timezone'], true) ? $name : null;
    }

    /**
     * The value of a parameter known by name (see kind()).
     *
     * @return array{bool, mixed}
     */
    public static function known(Parameter $parameter): array
    {
        if ($parameter->known === null) {
            return [false, null];
        }

        $container = Container::getInstance();

        return match ($parameter->known) {
            'user' => [true, self::user($parameter, null)],
            'locale' => [true, Settings::locale()],
            default => [true, $container->bound('config') ? (string) $container->make('config')->get('app.timezone', 'UTC') : 'UTC'],
        };
    }

    private static function user(Parameter $parameter, ?string $guard): ?Authenticatable
    {
        $container = Container::getInstance();
        $user = $container->bound('auth') ? $container->make('auth')->guard($guard)->user() : null;

        if ($user === null && ! $parameter->nullable) {
            throw new AuthenticationException('Unauthenticated.', $guard === null ? [] : [$guard]);
        }

        return $user;
    }

    /** @param  callable(string): mixed  $read */
    private static function first(Parameter $parameter, ?string $key, callable $read): mixed
    {
        $name = $parameter->name;

        foreach ($key !== null ? [$key] : array_unique([$name, Str::snake($name)]) as $candidate) {
            if (($value = $read($candidate)) !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function request(): ?Request
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;

        return $request instanceof Request ? $request : null;
    }
}
