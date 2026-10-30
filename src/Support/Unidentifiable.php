<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use RuntimeException;

/** @internal Thrown inside Fingerprint when a value has no reliable identity. */
final class Unidentifiable extends RuntimeException {}
