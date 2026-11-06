<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures;

use Illuminate\Contracts\Routing\UrlRoutable;

/** A model stand-in: found by id, or by slug for {reporter:slug}. */
final class Reporter implements UrlRoutable
{
    /** @var array<int, array{int, string, string}> */
    public const ROWS = [[1, 'ma-ngum', 'Ma Ngum'], [2, 'kola-ndi', 'Kola Ndi']];

    public function __construct(public readonly int $id = 0, public readonly string $slug = '', public readonly string $name = '') {}

    public function getRouteKey(): mixed
    {
        return $this->id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        foreach (self::ROWS as [$id, $slug, $name]) {
            if (($field === 'slug' ? $slug : (string) $id) === (string) $value) {
                return new self($id, $slug, $name);
            }
        }

        return null;
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?self
    {
        return null;
    }
}
