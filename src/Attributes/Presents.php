<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Names the model a ModelPresenter wraps, when its class name doesn't say so
 * (PostPresenter in Presenters/Models needs nothing):
 *
 *     #[Presents(Post::class)]
 *     final class StoryCard extends ModelPresenter {}
 *
 * Picked up by discovery, like #[ForView].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Presents
{
    /** @var list<class-string> */
    public readonly array $models;

    /** @param  class-string  ...$models */
    public function __construct(string ...$models)
    {
        $this->models = array_values($models);
    }
}
