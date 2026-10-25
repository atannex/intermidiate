<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Illuminate\Container\Container;

/** @internal Reads package config, with defaults when no app is booted. */
final class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        return $container->bound('config')
            ? $container->make('config')->get("intermidiate.{$key}", $default)
            : $default;
    }

    public static function pad(): int
    {
        return max(1, (int) self::get('pad', 2));
    }

    public static function locale(): string
    {
        $container = Container::getInstance();

        return $container->bound('translator') ? $container->make('translator')->getLocale() : 'en';
    }
}
