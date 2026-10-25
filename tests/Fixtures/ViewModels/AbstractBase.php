<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Fixtures\ViewModels;

use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\ViewModel;

/** Discovery skips abstract classes. */
#[ForView('never')]
abstract class AbstractBase extends ViewModel {}
