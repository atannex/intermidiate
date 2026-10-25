<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Attributes;

use Attribute;

/**
 * The method runs only if the view uses its value. The view receives a
 * Deferred proxy that resolves on first use (echo, loop, property access or
 * method call) and then behaves like the value.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Lazy {}
