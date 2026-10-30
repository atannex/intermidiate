<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Atannex\Intermidiate\Presenting\ModelPresenter;
use Attribute;

/**
 * Wraps a member's models in this ModelPresenter instead of the one found
 * for them, or wraps them when config intermidiate.present is off:
 *
 *     #[Present(StoryRow::class)]
 *     public function ledger(): Group
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Present
{
    /** @param  class-string<ModelPresenter>|null  $presenter  null: the one found for each model */
    public function __construct(public readonly ?string $presenter = null) {}
}
