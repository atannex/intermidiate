<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Presenting;

use ArrayAccess;
use Atannex\Intermidiate\Exceptions\PresenterException;
use Atannex\Intermidiate\Exceptions\UnsafeAccess;
use Atannex\Intermidiate\Intermidiate;
use Atannex\Intermidiate\Support\Member;
use Atannex\Intermidiate\Support\Schema;
use Atannex\Intermidiate\Support\Settings;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * How one model (or array) is displayed, wherever it is shown: its derived
 * display values as methods, defined once instead of in every template.
 *
 *     final class PostPresenter extends ModelPresenter
 *     {
 *         protected array $visible = ['id', 'title', 'permalink'];
 *
 *         public function permalink(): string { return route('posts.show', $this->subject->slug); }
 *         public function kicker(): string { return Str::upper($this->subject->category?->name ?? 'News'); }
 *     }
 *
 *     {{ $post->permalink }}  {{ $post->kicker }}  {{ $post->title }}   {{-- computed, computed, the model's --}}
 *
 * A page Presenter's models reach its view already wrapped in theirs (found
 * by name, #[Presents] or config intermidiate.models), and so do
 * collections and paginators. Computed fields run once per presenter.
 *
 * In a view, any other read goes to the model, as it would without the
 * presenter (Laravel's Model::preventAccessingMissingAttributes() still
 * applies), and route('posts.show', $post) uses the model's route key.
 * Serialized (toArray(), JSON, a Presenter's JSON) only what $visible
 * names leaves: attributes and computed fields alike.
 *
 * @template TSubject
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
abstract class ModelPresenter implements Arrayable, ArrayAccess, JsonSerializable, UrlRoutable
{
    /**
     * What serializes (toArray(), JSON, a Presenter's JSON): the subject's
     * attributes and this class's computed fields named here, in this
     * order, and nothing else. Attributes the model hides are refused.
     * With config intermidiate.legacy_serialization on, the whole subject
     * and every computed field serialize instead, as in 1.x.
     *
     * @var list<string>
     */
    protected array $visible = [];

    private ?Memo $memo = null;

    /**
     * @param  TSubject  $subject
     */
    final public function __construct(protected readonly mixed $subject) {}

    /**
     * @param  TSubject  $subject
     */
    public static function make(mixed $subject): static
    {
        return new static($subject);
    }

    /**
     * Wraps every item (a paginator's items too). Nulls are skipped.
     *
     * @param  iterable<TSubject>|Paginator|CursorPaginator  $subjects
     * @return Collection<int, static>
     */
    public static function collection(iterable|Paginator|CursorPaginator $subjects): Collection
    {
        $items = $subjects instanceof Paginator || $subjects instanceof CursorPaginator ? $subjects->items() : $subjects;

        return (new Collection($items))->filter(fn ($subject) => $subject !== null)->map(fn ($subject) => static::make($subject))->values();
    }

    /** @return TSubject */
    public function unwrap(): mixed
    {
        return $this->subject;
    }

    public function __get(string $name): mixed
    {
        $member = $this->member($name);

        if ($member !== null) {
            return $this->guarded(fn () => Engine::value($member, $this, $this->memo ??= new Memo));
        }

        return $this->read($name);
    }

    public function __isset(string $name): bool
    {
        if (($member = $this->member($name)) !== null) {
            return $this->guarded(fn () => Engine::value($member, $this, $this->memo ??= new Memo)) !== null;
        }

        return is_array($this->subject) ? isset($this->subject[$name]) : isset($this->subject->{$name});
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (! is_object($this->subject) || ! method_exists($this->subject, $method) && ! method_exists($this->subject, '__call')) {
            throw new PresenterException(static::class." has no method {$method}() and neither does its subject.");
        }

        return $this->subject->{$method}(...$arguments);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new PresenterException(static::class.' is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new PresenterException(static::class.' is read-only.');
    }

    /**
     * The fields named in $visible as JSON-compatible data (nested models
     * in their own presenter). With config intermidiate.legacy_serialization
     * on, the subject's whole array form plus every computed field, as in 1.x.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        return Engine::serialize($this, ! Settings::legacySerialization(), static::class);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getRouteKey(): mixed
    {
        if ($this->subject instanceof UrlRoutable) {
            return $this->subject->getRouteKey();
        }

        return is_array($this->subject) ? ($this->subject['id'] ?? null) : ($this->subject->id ?? null);
    }

    public function getRouteKeyName(): string
    {
        return $this->subject instanceof UrlRoutable ? $this->subject->getRouteKeyName() : 'id';
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        $model = $this->subject instanceof UrlRoutable ? $this->subject->resolveRouteBinding($value, $field) : null;

        return $model === null ? null : static::make($model);
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?static
    {
        $model = $this->subject instanceof UrlRoutable ? $this->subject->resolveChildRouteBinding($childType, $value, $field) : null;

        return $model === null ? null : static::make($model);
    }

    /**
     * @internal The fields to serialize, before serializing: what toArray()
     * and a Presenter's JSON walk.
     *
     * @return array<string, mixed>
     */
    public function declaredFields(bool $strict): array
    {
        if (! $strict) {
            $data = match (true) {
                is_array($this->subject) => $this->subject,
                $this->subject instanceof Arrayable => $this->subject->toArray(),
                $this->subject instanceof JsonSerializable => (array) $this->subject->jsonSerialize(),
                default => [],
            };

            return [...$data, ...$this->guarded(fn () => Engine::values(Schema::of($this), $this, $this->memo ??= new Memo, json: true))];
        }

        return $this->guarded(function (): array {
            $schema = Schema::of($this);
            $data = [];

            foreach ($this->visible as $key) {
                $member = $schema->members[$key] ?? null;

                if ($member === null) {
                    $data[$key] = Models::wrapMember($this->attribute($key), null);
                } elseif ($member->kind === Member::CALLABLE || $member->viewOnly) {
                    throw new PresenterException(static::class." lists \"{$key}\" in \$visible, but it ".($member->viewOnly ? 'is #[ViewOnly]' : 'takes parameters').' and can\'t be serialized.');
                } else {
                    $data[$key] = Engine::value($member, $this, $this->memo ??= new Memo);
                }
            }

            return $data;
        });
    }

    /** A subject attribute listed in $visible: never one the model hides. */
    private function attribute(string $key): mixed
    {
        if ($this->subject instanceof Model && in_array($key, $this->subject->getHidden(), true)) {
            throw UnsafeAccess::hidden(static::class, $this->subject::class, $key);
        }

        return $this->read($key);
    }

    /**
     * Context a #[Cached] member's value depends on beyond the inputs, the
     * locale and the signed-in user, such as the current tenant:
     *
     *     protected function cacheScope(): array { return ['tenant' => tenant()->id]; }
     *
     * @return array<string, mixed>
     */
    protected function cacheScope(): array
    {
        return [];
    }

    /**
     * Wraps related models in their model presenter, for computed fields
     * that hand them on (their values aren't wrapped on their own):
     *
     *     public function lessons(): Collection { return $this->present($this->subject->lessons); }
     *
     * @param  class-string<ModelPresenter>|null  $presenter  this one instead of the one found for each model
     */
    protected function present(mixed $value, ?string $presenter = null): mixed
    {
        $container = Container::getInstance();

        if ($container->bound(Intermidiate::class)) {
            return $container->make(Intermidiate::class)->wrap($value, $presenter);
        }

        return $presenter === null ? $value : (is_iterable($value) ? $presenter::collection($value) : $presenter::make($value));
    }

    /** One of the subject's own values; strict, an unloaded relation fails instead of querying. */
    private function read(string $key): mixed
    {
        if (is_array($this->subject)) {
            return $this->subject[$key] ?? null;
        }

        if ($this->subject instanceof Model && ! $this->subject->relationLoaded($key) && $this->subject->isRelation($key) && Settings::guardsLazyLoading()) {
            throw UnsafeAccess::unloaded(static::class, $this->subject::class, $key);
        }

        return $this->subject->{$key};
    }

    /**
     * Runs a computed field with lazy loading turned off for the subject
     * and every model loaded with it (strict only), so the field reads only
     * what the application loaded. Laravel then throws
     * LazyLoadingViolationException, or calls the app's own
     * Model::handleLazyLoadingViolationUsing() handler.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function guarded(Closure $work): mixed
    {
        if (! $this->subject instanceof Model || ! Settings::guardsLazyLoading()) {
            return $work();
        }

        $models = [];
        self::loaded($this->subject, $models);
        $previous = [];

        foreach ($models as $id => $model) {
            $previous[$id] = $model->preventsLazyLoading;
            $model->preventsLazyLoading = true;
        }

        try {
            return $work();
        } finally {
            foreach ($models as $id => $model) {
                $model->preventsLazyLoading = $previous[$id];
            }
        }
    }

    /** @param  array<int, Model>  $models  the model and its loaded relations, at any depth */
    private static function loaded(Model $model, array &$models): void
    {
        if (isset($models[$id = spl_object_id($model)]) || count($models) >= 1000) {
            return;
        }

        $models[$id] = $model;

        foreach ($model->getRelations() as $related) {
            foreach ($related instanceof Model ? [$related] : (is_iterable($related) ? $related : []) as $item) {
                if ($item instanceof Model) {
                    self::loaded($item, $models);
                }
            }
        }
    }

    private function member(string $name): ?Member
    {
        $member = Schema::of($this)->member($name);

        return $member !== null && $member->kind !== Member::CALLABLE ? $member : null;
    }
}
