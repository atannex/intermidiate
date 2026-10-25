<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/** Keeps a public property or method out of the view data and the JSON. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Hidden {}
