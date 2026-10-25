<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Config;
use Atannex\Intermidiate\Attributes\CurrentUser;
use Atannex\Intermidiate\Attributes\Old;
use Atannex\Intermidiate\Attributes\Session;
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
    public static function marked(ReflectionParameter $parameter): array
    {
        $container = Container::getInstance();

        if ($attribute = self::attribute($parameter, Config::class)) {
            $config = $container->bound('config') ? $container->make('config') : null;

            return $config !== null && $config->has($attribute->key) ? [true, $config->get($attribute->key)] : [false, null];
        }

        if ($attribute = self::attribute($parameter, Old::class)) {
            $request = self::request();
            $value = $request?->hasSession() ? self::first($parameter, $attribute->key, fn (string $key) => $request->old($key)) : null;

            return $value === null ? [false, null] : [true, $value];
        }

        if ($attribute = self::attribute($parameter, Session::class)) {
            $request = self::request();
            $value = $request?->hasSession() ? self::first($parameter, $attribute->key, fn (string $key) => $request->session()->get($key)) : null;

            return $value === null ? [false, null] : [true, $value];
        }

        if ($attribute = self::attribute($parameter, CurrentUser::class)) {
            return [true, self::user($parameter, $attribute->guard)];
        }

        return [false, null];
    }

    /**
     * Unmarked parameters known by name: a user-typed $user (or $viewer…),
     * string $locale and string $timezone.
     *
     * @return array{bool, mixed}
     */
    public static function known(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $name = $parameter->getName();

        if (! $type instanceof ReflectionNamedType) {
            return [false, null];
        }

        if (! $type->isBuiltin() && in_array($name, self::USER_NAMES, true) && is_a($type->getName(), Authenticatable::class, true)) {
            return [true, self::user($parameter, null)];
        }

        if ($type->getName() !== 'string') {
            return [false, null];
        }

        $container = Container::getInstance();

        return match ($name) {
            'locale' => [true, Settings::locale()],
            'timezone' => [true, $container->bound('config') ? (string) $container->make('config')->get('app.timezone', 'UTC') : 'UTC'],
            default => [false, null],
        };
    }

    private static function user(ReflectionParameter $parameter, ?string $guard): ?Authenticatable
    {
        $container = Container::getInstance();
        $user = $container->bound('auth') ? $container->make('auth')->guard($guard)->user() : null;

        if ($user === null && ! $parameter->allowsNull()) {
            throw new AuthenticationException('Unauthenticated.', $guard === null ? [] : [$guard]);
        }

        return $user;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private static function attribute(ReflectionParameter $parameter, string $class): ?object
    {
        return ($parameter->getAttributes($class)[0] ?? null)?->newInstance();
    }

    /** @param  callable(string): mixed  $read */
    private static function first(ReflectionParameter $parameter, ?string $key, callable $read): mixed
    {
        $name = $parameter->getName();

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
