<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * Binds a Presenter to one or more views (wildcards allowed). When such a view
 * renders, the Presenter is built from the view's data and its members are
 * added to it. Picked up by discovery (config intermidiate.discover).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ForView
{
    /** @var list<string> */
    public readonly array $views;

    public function __construct(string ...$views)
    {
        $this->views = array_values($views);
    }
}
