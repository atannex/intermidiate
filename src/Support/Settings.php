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

    /**
     * Whether model presenters refuse to lazy load (config intermidiate.strict).
     * On unless set to false.
     */
    public static function guardsLazyLoading(): bool
    {
        return self::get('strict', true) !== false;
    }

    /**
     * Whether serialization uses the 1.x rules: whole models, every computed
     * field, raw models allowed (config intermidiate.legacy_serialization).
     * Only a literal true turns it on, so "false" from an env file can't.
     */
    public static function legacySerialization(): bool
    {
        return self::get('legacy_serialization', false) === true;
    }

    public static function pad(): int
    {
        return max(1, (int) self::get('pad', 2));
    }

    /**
     * The zone dates are shown in: intermidiate.timezone when an app sets one
     * (say, per visitor), else app.timezone. Storage stays on app.timezone.
     */
    public static function timezone(): string
    {
        $container = Container::getInstance();
        $config = $container->bound('config') ? $container->make('config') : null;

        return (string) ($config?->get('intermidiate.timezone') ?: $config?->get('app.timezone') ?: 'UTC');
    }

    public static function locale(): string
    {
        $container = Container::getInstance();

        return $container->bound('translator') ? $container->make('translator')->getLocale() : 'en';
    }
}
