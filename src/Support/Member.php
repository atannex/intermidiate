<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Cached;

/** One exported member of a Presenter or presenter. */
final class Member
{
    public const PROPERTY = 'property';

    public const METHOD = 'method';

    public const CALLABLE = 'callable';

    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $kind,
        public readonly ?string $type,
        public readonly bool $lazy = false,
        public readonly bool $viewOnly = false,
        /** null: wrap models in the presenter found for them; false: #[Raw]; a class: #[Present(Class)]; true: #[Present] */
        public readonly string|bool|null $present = null,
        public readonly ?Cached $cached = null,
    ) {}

    /** How the view sees it, for presenter:list. */
    public function describe(): string
    {
        $type = $this->type ?? 'mixed';

        return match ($this->kind) {
            self::CALLABLE => "Closure → {$type}",
            default => $type,
        }.($this->lazy ? ' (lazy)' : '').($this->viewOnly ? ' (view only)' : '').($this->cached ? ' (cached)' : '');
    }
}
