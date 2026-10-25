<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\Presenters;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Presenter;

/** Discovery skips abstract classes. */
#[ForView('never')]
abstract class AbstractBase extends Presenter {}
