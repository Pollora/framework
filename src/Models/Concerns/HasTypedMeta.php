<?php

declare(strict_types=1);

namespace Pollora\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use LogicException;
use Pollora\Colt\Model\Comment;
use Pollora\Colt\Model\Post;
use Pollora\Colt\Model\Term;
use Pollora\Colt\Model\User;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaValidatorInterface;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Enums\MetaValueType;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaRecord;
use Pollora\Meta\Domain\Models\MetaSchema;
use Pollora\Meta\Domain\Services\MetaValueCaster;

/**
 * Typed meta as attributes of a `Pollora\Models` model: the meta its object
 * carries according to `#[Meta]` declarations (`#[PostType]` of its post type,
 * `#[PostMeta]`, `#[UserMeta]`…) are read with their PHP type and written
 * through WordPress's meta API.
 *
 *     class Event extends \Pollora\Models\Post
 *     {
 *         protected $postType = 'event';
 *     }
 *
 *     $event = Event::find($id);
 *     $event->capacity;                    // int, or by its key: $event->starts_at
 *     $event->capacity = 250;              // type and rules checked now, written on save()
 *     $event->save();
 *     Event::whereMeta('capacity', '>=', 100)->get();
 *
 * A model whose class is known to carry typed meta (a post model with the
 * `$postType` of a declared schema, users and comments with a `#[UserMeta]` or
 * `#[CommentMeta]`) reads through the object cache, primed in one query for
 * all the models loaded together, and no longer eager loads the `meta`
 * relation. Other models, and keys no
 * `#[Meta]` declares, keep Colt's behaviour (`$post->some_key` reads the raw
 * value): a project without typed meta sees no change.
 *
 * @experimental The API may still change before it is declared stable.
 */
trait HasTypedMeta
{
    /**
     * @var array<class-string, array<string, mixed>> Values waiting for save(), by schema and property
     */
    private array $pendingTypedMeta = [];

    /**
     * @var array<class-string, MetaRecord> Records already read, by schema
     */
    private array $typedMetaRecords = [];

    /**
     * Whether the class is known to carry typed meta: the object cache then
     * replaces the eager loaded `meta` relation.
     */
    private bool $carriesTypedMeta = false;

    /**
     * @var array<int, true> IDs of the models loaded since the meta cache was last primed
     */
    private static array $typedMetaBatch = [];

    /**
     * Writes the pending typed meta once the model is saved, when its ID is known.
     */
    public static function bootHasTypedMeta(): void
    {
        static::saved(static function (self $model): void {
            $model->saveTypedMeta();
        });

        // Models loaded together are primed together, on the first typed read.
        static::retrieved(static function (self $model): void {
            if ($model->carriesTypedMeta) {
                self::$typedMetaBatch[(int) $model->getKey()] = true;
            }
        });
    }

    /**
     * The object cache replaces the eager loaded `meta` relation, for a class known
     * to carry typed meta.
     */
    public function initializeHasTypedMeta(): void
    {
        $staticSubtype = $this->typedMetaObjectType() === MetaObjectType::Post ? $this->declaredPostType() : null;

        $this->carriesTypedMeta = resolve(MetaSchemaRepository::class)->forObject($this->typedMetaObjectType(), $staticSubtype) !== [];

        if ($this->carriesTypedMeta) {
            $this->with = array_values(array_diff($this->with, ['meta']));
        }
    }

    /**
     * A typed meta, read with its PHP type, or the attribute as Eloquent reads it.
     */
    public function getAttribute($key): mixed
    {
        $schema = $this->typedMetaSchemaFor($key);

        if (! $schema instanceof MetaSchema) {
            return parent::getAttribute($key);
        }

        $property = $schema->find($key)->property;

        if (array_key_exists($property, $this->pendingTypedMeta[$schema->declaringClass] ?? [])) {
            return $this->pendingTypedMeta[$schema->declaringClass][$property];
        }

        if (! $this->exists) {
            return $schema->definitions[$property]->default;
        }

        $this->primeTypedMetaCache();

        return ($this->typedMetaRecords[$schema->declaringClass] ??= resolve(MetaAccessor::class)->record($schema, (int) $this->getKey()))->get($property);
    }

    /**
     * A typed meta, checked now and written on save(), or the attribute as Eloquent sets it.
     */
    public function setAttribute($key, $value): mixed
    {
        $schema = $this->typedMetaSchemaFor($key);

        if (! $schema instanceof MetaSchema) {
            return parent::setAttribute($key, $value);
        }

        $definition = $schema->find($key);

        if (resolve(MetaValueCaster::class)->toStorage($definition, $value) !== null) {
            resolve(MetaValidatorInterface::class)->validate($definition, $value);
        }

        $this->pendingTypedMeta[$schema->declaringClass][$definition->property] = $value;

        return $this;
    }

    /**
     * Whether a typed meta is waiting to be written.
     */
    public function hasPendingTypedMeta(): bool
    {
        return $this->pendingTypedMeta !== [];
    }

    /**
     * Writes the pending typed meta through WordPress's meta API.
     */
    public function saveTypedMeta(): void
    {
        foreach ($this->pendingTypedMeta as $class => $values) {
            $schema = resolve(MetaSchemaRepository::class)->forClass($class);

            if ($schema instanceof MetaSchema) {
                $record = $this->typedMetaRecords[$class] ??= resolve(MetaAccessor::class)->record($schema, (int) $this->getKey());
                $record->fill($values)->save();
            }
        }

        $this->pendingTypedMeta = [];
    }

    /**
     * Filters on a typed meta, compared as stored: numbers as numbers, dates in
     * UTC. A model without the meta is not matched, even if its default would be.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException When the meta is not declared or the operator is not supported
     */
    protected function scopeWhereMeta(Builder $query, string $name, mixed $operator, mixed $value = null): Builder
    {
        if (func_num_args() === 3) {
            [$operator, $value] = ['=', $operator];
        }

        if (! in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>='], true)) {
            throw new InvalidArgumentException(sprintf('whereMeta() does not support the operator "%s".', $operator));
        }

        $definition = $this->typedMetaDefinitionForQuery($name);

        if ($value === null) {
            return match ($operator) {
                '=' => $query->whereDoesntHave('meta', static fn (Builder $meta): Builder => $meta->where('meta_key', $definition->key)),
                '!=', '<>' => $query->whereHas('meta', static fn (Builder $meta): Builder => $meta->where('meta_key', $definition->key)),
                default => throw new InvalidArgumentException(sprintf('whereMeta() compares null with "=" or "!=" only, not "%s".', $operator)),
            };
        }

        $stored = resolve(MetaValueCaster::class)->toStorage($definition, $value);

        return $query->whereHas('meta', static function (Builder $meta) use ($definition, $operator, $stored): void {
            $meta->where('meta_key', $definition->key);

            match ($definition->valueType) {
                MetaValueType::Integer => $meta->whereRaw('CAST(meta_value AS SIGNED) '.$operator.' ?', [(int) $stored]),
                MetaValueType::Number => $meta->whereRaw('CAST(meta_value AS DECIMAL(65, 10)) '.$operator.' ?', [(float) $stored]),
                default => $meta->where('meta_value', $operator, $stored),
            };
        });
    }

    /**
     * Loads the meta of every model loaded since the last typed read in one query,
     * so reading a meta across a collection costs one query, not one per model.
     */
    private function primeTypedMetaCache(): void
    {
        if (self::$typedMetaBatch === [] || ! function_exists('update_meta_cache')) {
            return;
        }

        $ids = array_keys(self::$typedMetaBatch);
        self::$typedMetaBatch = [];
        update_meta_cache($this->typedMetaObjectType()->value, $ids);
    }

    /**
     * The schema declaring a meta under that name for this object, if any.
     */
    private function typedMetaSchemaFor(string $key): ?MetaSchema
    {
        $schemas = resolve(MetaSchemaRepository::class);
        $objectType = $this->typedMetaObjectType();

        // Cheap test first: every attribute read goes through here.
        if (! $schemas->declares($objectType, $key)) {
            return null;
        }

        foreach ($schemas->forObject($objectType, $this->typedMetaSubtype()) as $schema) {
            if ($schema->find($key) instanceof MetaDefinition) {
                return $schema;
            }
        }

        return null;
    }

    private function typedMetaDefinitionForQuery(string $name): MetaDefinition
    {
        $schema = $this->typedMetaSchemaFor($name);

        return $schema?->find($name) ?? throw new InvalidArgumentException(sprintf('%s has no typed meta named "%s".', static::class, $name));
    }

    private function typedMetaObjectType(): MetaObjectType
    {
        return match (true) {
            $this instanceof Post => MetaObjectType::Post,
            $this instanceof Term => MetaObjectType::Term,
            $this instanceof User => MetaObjectType::User,
            $this instanceof Comment => MetaObjectType::Comment,
            default => throw new LogicException(sprintf('%s uses HasTypedMeta but is not a post, term, user or comment model.', static::class)),
        };
    }

    /**
     * The post type a post model is bound to (`protected $postType = 'event'`), if any.
     */
    private function declaredPostType(): ?string
    {
        $postType = get_object_vars($this)['postType'] ?? null;

        return is_string($postType) && $postType !== '' ? $postType : null;
    }

    /**
     * The post type or taxonomy of the object, read without going through
     * getAttribute(), which calls this.
     */
    private function typedMetaSubtype(): ?string
    {
        return match ($this->typedMetaObjectType()) {
            MetaObjectType::Post => $this->attributes['post_type'] ?? $this->declaredPostType(),
            MetaObjectType::Term => $this->exists ? $this->getRelationValue('taxonomy')?->getAttribute('taxonomy') : null,
            default => null,
        };
    }
}
