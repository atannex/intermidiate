<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Atannex\Intermidiate\Attributes\Cached;
use Atannex\Intermidiate\Attributes\Expose;
use Atannex\Intermidiate\Attributes\ForView;
use Atannex\Intermidiate\Attributes\Hidden;
use Atannex\Intermidiate\Attributes\Lazy;
use Atannex\Intermidiate\Attributes\Present;
use Atannex\Intermidiate\Attributes\Raw;
use Atannex\Intermidiate\Attributes\ViewOnly;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Presenting\ModelPresenter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * What a Presenter (or presenter) exports, read once per class by reflection:
 * public properties become values, public methods without parameters become
 * memoized values and public methods with parameters become closures.
 */
final class Schema
{
    /** @var array<class-string, self> */
    private static array $cache = [];

    /** @var array<string, Member>|null members by PHP name and snake_case, built on the first miss */
    private ?array $aliases = null;

    /**
     * @param  array<string, Member>  $members  keyed by variable name
     * @param  list<string>  $views  from #[ForView]
     */
    private function __construct(
        public readonly string $class,
        public readonly array $members,
        public readonly array $views,
    ) {}

    public static function of(string|object $class): self
    {
        $class = is_object($class) ? $class::class : $class;

        return self::$cache[$class] ??= self::reflect($class);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public function member(string $key): ?Member
    {
        return $this->members[$key] ?? ($this->aliases ??= $this->byName())[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    /** @return array<string, Member> members keyed by PHP name, plus snake_case aliases */
    private function byName(): array
    {
        $map = [];

        foreach ($this->members as $member) {
            $map[$member->name] = $member;
            $map[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $member->name))] ??= $member;
        }

        return $map;
    }

    private static function reflect(string $class): self
    {
        $reflection = new ReflectionClass($class);
        $base = $reflection->isSubclassOf(ModelPresenter::class) ? ModelPresenter::class : Presenter::class;
        $reserved = [];

        foreach ((new ReflectionClass($base))->getMethods() as $method) {
            $reserved[$method->getName()] = true;
        }

        $members = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || self::has($property, Hidden::class)) {
                continue;
            }

            self::add($members, $class, new Member(
                self::key($property, $property->getName()),
                $property->getName(),
                Member::PROPERTY,
                $property->hasType() ? (string) $property->getType() : null,
                false,
                self::has($property, ViewOnly::class),
                self::present($property),
            ));
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($method->isStatic() || str_starts_with($name, '__') || isset($reserved[$name]) || self::has($method, Hidden::class)) {
                continue;
            }

            self::add($members, $class, new Member(
                self::key($method, $name),
                $name,
                $method->getNumberOfParameters() === 0 ? Member::METHOD : Member::CALLABLE,
                $method->hasReturnType() ? (string) $method->getReturnType() : null,
                self::has($method, Lazy::class),
                self::has($method, ViewOnly::class),
                self::present($method),
                ($method->getAttributes(Cached::class)[0] ?? null)?->newInstance(),
            ));
        }

        $views = [];

        foreach ($reflection->getAttributes(ForView::class) as $attribute) {
            array_push($views, ...$attribute->newInstance()->views);
        }

        return new self($class, $members, array_values(array_unique($views)));
    }

    /**
     * @param  array<string, Member>  $members
     */
    private static function add(array &$members, string $class, Member $member): void
    {
        if (isset($members[$member->key])) {
            throw new PresenterException("{$class} exports \"{$member->key}\" twice ({$members[$member->key]->name} and {$member->name}). Rename one with #[Expose].");
        }

        $members[$member->key] = $member;
    }

    private static function key(ReflectionProperty|ReflectionMethod $member, string $name): string
    {
        $expose = $member->getAttributes(Expose::class)[0] ?? null;

        return $expose ? $expose->newInstance()->as : $name;
    }

    private static function present(ReflectionProperty|ReflectionMethod $member): string|bool|null
    {
        if (self::has($member, Raw::class)) {
            return false;
        }

        $present = $member->getAttributes(Present::class)[0] ?? null;

        return $present ? ($present->newInstance()->presenter ?? true) : null;
    }

    private static function has(ReflectionProperty|ReflectionMethod $member, string $attribute): bool
    {
        return $member->getAttributes($attribute) !== [];
    }
}
