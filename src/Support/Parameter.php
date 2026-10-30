<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Config;
use Atannex\Intermidiate\Attributes\CurrentUser;
use Atannex\Intermidiate\Attributes\Old;
use Atannex\Intermidiate\Attributes\Query;
use Atannex\Intermidiate\Attributes\Session;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * @internal A constructor parameter as Hydrator needs it, read once per class:
 * its spellings, its attributes already instantiated and what its type means.
 */
final class Parameter
{
    /**
     * @param  list<string>  $spellings  the name, then its snake_case and camelCase forms
     * @param  class-string|null  $class  the type, when it's a single class
     * @param  bool  $service  a class type that isn't a model, enum, date or collection
     * @param  'user'|'locale'|'timezone'|null  $known  filled by name, with no attribute
     */
    private function __construct(
        public readonly ReflectionParameter $reflection,
        public readonly string $name,
        public readonly int $position,
        public readonly ?ReflectionType $type,
        public readonly array $spellings,
        public readonly bool $variadic,
        public readonly bool $optional,
        public readonly bool $nullable,
        public readonly ?string $class,
        public readonly bool $service,
        public readonly Config|Old|Session|CurrentUser|null $marker,
        public readonly ?Query $query,
        public readonly ?string $known,
    ) {}

    public static function of(ReflectionParameter $parameter): self
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

        return new self(
            $parameter,
            $name,
            $parameter->getPosition(),
            $type,
            array_values(array_unique([$name, Str::snake($name), Str::camel($name)])),
            $parameter->isVariadic(),
            $parameter->isDefaultValueAvailable(),
            $parameter->allowsNull(),
            $class,
            $class !== null && self::isService($class),
            self::marker($parameter),
            ($parameter->getAttributes(Query::class)[0] ?? null)?->newInstance(),
            Ambient::kind($parameter),
        );
    }

    /** The first of #[Config], #[Old], #[Session] and #[CurrentUser] it carries. */
    private static function marker(ReflectionParameter $parameter): Config|Old|Session|CurrentUser|null
    {
        foreach ([Config::class, Old::class, Session::class, CurrentUser::class] as $attribute) {
            if (($found = $parameter->getAttributes($attribute)[0] ?? null) !== null) {
                return $found->newInstance();
            }
        }

        return null;
    }

    private static function isService(string $class): bool
    {
        return ! enum_exists($class)
            && ! is_a($class, 'Illuminate\Database\Eloquent\Model', true)
            && ! is_a($class, \DateTimeInterface::class, true)
            && ! is_a($class, \Traversable::class, true)
            && ! is_a($class, Arrayable::class, true);
    }
}
