<?php

declare(strict_types=1);

namespace Atannex\Intermidiate;

use ArrayAccess;
use ArrayIterator;
use Atannex\Intermidiate\Exceptions\UnknownMember;
use Atannex\Intermidiate\Exceptions\ViewModelException;
use Atannex\Intermidiate\Presenting\Presenter;
use Atannex\Intermidiate\Slicing\Partition;
use Atannex\Intermidiate\Support\Deferred;
use Atannex\Intermidiate\Support\Hydrator;
use Atannex\Intermidiate\Support\Member;
use Atannex\Intermidiate\Support\Schema;
use Atannex\Intermidiate\Support\Settings;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Everything a view needs, worked out before it renders.
 *
 * Public properties and public methods are the view's variables: a method
 * without parameters is called once and its result shared (so `$vm->lead`
 * and `$lead` are the same value), a method with parameters arrives as a
 * closure. Constructor parameters are the inputs; give them types and the
 * package checks (and, from strings, converts) whatever is passed in.
 *
 *     return ArchiveViewModel::make(posts: $posts, year: $year)->view('date');
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
abstract class ViewModel implements Arrayable, ArrayAccess, IteratorAggregate, JsonSerializable, Responsable
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    /** @var array<string, mixed> */
    private array $extra = [];

    private ?string $template = null;

    private bool $prepared = false;

    /**
     * Builds the ViewModel by name (or position), checking every value
     * against its declared type. Services the constructor type-hints come
     * from the container.
     */
    public static function make(mixed ...$parameters): static
    {
        return Hydrator::build(static::class, $parameters);
    }

    /**
     * Builds the ViewModel from loose data, such as a view's variables or a
     * request: keys may be snake_case or camelCase, and extra keys are ignored.
     *
     * @param  array<string, mixed>|Arrayable<string, mixed>  $data
     */
    public static function from(array|Arrayable $data): static
    {
        return Hydrator::build(static::class, $data instanceof Arrayable ? $data->toArray() : $data, loose: true);
    }

    /**
     * The view this ViewModel renders when returned from a controller.
     *
     * @param  array<string, mixed>  $data  extra variables
     */
    public function view(string $view, array $data = []): static
    {
        $this->template = $view;

        return $this->with($data);
    }

    /**
     * Adds variables that aren't members.
     *
     * @param  string|array<string, mixed>  $key
     */
    public function with(string|array $key, mixed $value = null): static
    {
        $this->extra = [...$this->extra, ...(is_array($key) ? $key : [$key => $value])];

        return $this;
    }

    public function template(): ?string
    {
        if ($this->template !== null) {
            return $this->template;
        }

        $views = Schema::of($this)->views;

        return count($views) === 1 && ! str_contains($views[0], '*') ? $views[0] : null;
    }

    /**
     * The view variables: every member, the extras, and the ViewModel itself
     * under config intermidiate.variable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (Schema::of($this)->members as $key => $member) {
            $data[$key] = $member->lazy
                ? new Deferred(fn () => $this->resolve($member))
                : $this->resolve($member);
        }

        $data = [...$data, ...$this->extra];

        if (is_string($variable = Settings::get('variable', 'vm')) && $variable !== '') {
            $data[$variable] = $this;
        }

        return $data;
    }

    /**
     * Only these variables, for `@include('partial', $vm->only('lead', 'beside'))`.
     *
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->get($key);
        }

        return $data;
    }

    /**
     * Every variable except these.
     *
     * @return array<string, mixed>
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /** One variable by name (camelCase or snake_case). */
    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->extra)) {
            return $this->extra[$key];
        }

        $member = Schema::of($this)->member($key)
            ?? throw UnknownMember::for(static::class, $key, [...Schema::of($this)->keys(), ...array_keys($this->extra)]);

        return $this->resolve($member);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->extra) || Schema::of($this)->member($key) !== null;
    }

    public function toView(): View
    {
        $view = $this->template()
            ?? throw new ViewModelException(static::class.' has no view. Call ->view(\'name\') or add #[ForView(\'name\')].');

        return Container::getInstance()->make(Factory::class)->make($view, $this->toArray());
    }

    public function render(): string
    {
        return $this->toView()->render();
    }

    public function toResponse($request): Response|JsonResponse
    {
        $container = Container::getInstance();

        if ($container->bound(Intermidiate::class)) {
            $container->make(Intermidiate::class)->track($this);
        }

        if (Settings::get('json', true) && $request->expectsJson()) {
            return new JsonResponse($this->jsonSerialize());
        }

        return new Response($this->toView());
    }

    /**
     * The members as JSON: closures and #[ViewOnly] members are left out.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [];

        foreach (Schema::of($this)->members as $key => $member) {
            if ($member->kind !== Member::CALLABLE && ! $member->viewOnly) {
                $data[$key] = $this->resolve($member);
            }
        }

        foreach ($this->extra as $key => $value) {
            if (! $value instanceof Closure) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name) && $this->get($name) !== null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ViewModelException(static::class.' is read-only in the view. Use ->with() before rendering.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new ViewModelException(static::class.' is read-only in the view.');
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Runs once, before the first member is read: the place to load
     * relations or do work several members share.
     */
    protected function prepare(): void {}

    /**
     * Remembers a value for the life of this ViewModel, for protected
     * helpers that several members call.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function once(string $key, Closure $callback): mixed
    {
        $key = "\0".$key;

        if (! array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $callback();
        }

        return $this->resolved[$key];
    }

    /**
     * Starts a partition of these items (a paginator keeps its numbering).
     *
     * @param  iterable<mixed>|Paginator|CursorPaginator  $items
     */
    protected function partition(iterable $items): Partition
    {
        return Partition::of($items);
    }

    /**
     * Wraps each item in a presenter.
     *
     * @template P of Presenter
     *
     * @param  iterable<mixed>  $items
     * @param  class-string<P>  $presenter
     * @return Collection<int, P>
     */
    protected function present(iterable $items, string $presenter): Collection
    {
        return $presenter::collection($items);
    }

    private function resolve(Member $member): mixed
    {
        if (! $this->prepared) {
            $this->prepared = true;
            $this->prepare();
        }

        if ($member->kind === Member::PROPERTY) {
            return $this->{$member->name};
        }

        if ($member->kind === Member::CALLABLE) {
            return $this->resolved[$member->name] ??= Closure::fromCallable([$this, $member->name]);
        }

        if (! array_key_exists($member->name, $this->resolved)) {
            $this->resolved[$member->name] = $this->{$member->name}();
        }

        return $this->resolved[$member->name];
    }
}
