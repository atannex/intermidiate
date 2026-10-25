<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Extra\Tidy;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Presenter;

/** Bound by #[ForView] until tidy drops it (the comment must not keep the import). */
#[ForView('tidy.sample')]
final class SamplePresenter extends Presenter
{
    public function greeting(): string
    {
        return 'Hi';
    }
}
