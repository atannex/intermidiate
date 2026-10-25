<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Presenters\Models;

use Atannex\Intermidiate\Attributes\Presents;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use Atannex\Intermidiate\Tests\Fixtures\Editor;

/** Found through the attribute: its name doesn't say Editor. */
#[Presents(Editor::class)]
final class EditorCard extends ModelPresenter
{
    public function badge(): string
    {
        return 'Ed. '.$this->name;
    }
}
