<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\View;

use Atannex\Intermidiate\ViewModel;

/**
 * @internal Backs the @viewmodel directive: builds a ViewModel from the
 * variables in scope (for partials and anonymous components that have no
 * controller) and returns its variables to extract.
 */
final class Inline
{
    /**
     * @param  array<string, mixed>  $scope
     * @param  class-string<ViewModel>  $class
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
