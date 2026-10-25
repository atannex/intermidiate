<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\Presenter;

/**
 * @internal Backs the @presenter directive: builds a Presenter from the
 * variables in scope (for partials and anonymous components that have no
 * controller) and returns its variables to extract.
 */
final class Inline
{
    /**
     * @param  array<string, mixed>  $scope
     * @param  class-string<Presenter>  $class
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    public static function variables(array $scope, string $class, array $with = []): array
    {
        $data = [];

        foreach ($scope as $key => $value) {
            if (! str_starts_with($key, '__')) {
                $data[$key] = $value;
            }
        }

        return $class::from([...$data, ...$with])->toArray();
    }
}
