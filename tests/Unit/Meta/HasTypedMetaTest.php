<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Events\Dispatcher;
use Pollora\Meta\Application\Services\MetaAccessor;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Exceptions\InvalidMetaValueException;
use Pollora\Meta\Domain\Services\MetaValueCaster;
use Pollora\Models\Post;
use Pollora\Models\User;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\MemberProfile;

/**
 * A post model bound to the `event` post type, as a project would write it.
 */
#[Connection('wordpress')]
final class EventModel extends Post
{
    protected $postType = 'event';
}

/**
 * An in-memory meta store recording writes.
 */
final class InMemoryMetaStore implements MetaStoreInterface
{
    /** @var array<string, string> */
    public array $values = [];

    public function get(MetaObjectType $objectType, int $objectId, string $key): mixed
    {
        return $this->values["{$objectType->value}:{$objectId}:{$key}"] ?? null;
    }

    public function update(MetaObjectType $objectType, int $objectId, string $key, string $value): void
    {
        $this->values["{$objectType->value}:{$objectId}:{$key}"] = $value;
    }

    public function delete(MetaObjectType $objectType, int $objectId, string $key): void
    {
        unset($this->values["{$objectType->value}:{$objectId}:{$key}"]);
    }
}

/**
 * An existing model with the given raw attributes.
 *
 * @template TModel of Model
 *
 * @param  class-string<TModel>  $class
 * @param  array<string, mixed>  $attributes
 * @return TModel
 */
function existingModel(string $class, array $attributes): Model
{
    $model = new $class;
    $model->setRawAttributes($attributes, true);
    $model->exists = true;

    return $model;
}

beforeEach(function (): void {
    // Colt asks the container for its version to tell Laravel from Lumen.
    $this->previousContainer = Container::getInstance();
    $container = new class extends Container
    {
        public function version(): string
        {
            return '13.0.0';
        }
    };
    Container::setInstance($container);

    $this->repository = new MetaSchemaRepository;
    $this->repository->add((new MetaSchemaBuilder)->build(Event::class));
    $this->repository->add((new MetaSchemaBuilder)->build(MemberProfile::class));

    $this->store = new InMemoryMetaStore;
    $container->instance(MetaSchemaRepository::class, $this->repository);
    $container->instance(MetaValueCaster::class, new MetaValueCaster);
    $container->instance(MetaAccessor::class, new MetaAccessor($this->repository, new MetaSchemaBuilder, $this->store, new MetaValueCaster));
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

it('reads a declared meta with its PHP type, by property name or key', function (): void {
    $this->store->values['post:42:capacity'] = '250';
    $this->store->values['post:42:sold_out'] = '1';
    $event = existingModel(EventModel::class, ['ID' => 42, 'post_type' => 'event']);

    expect($event->capacity)->toBe(250)
        ->and($event->soldOut)->toBeTrue()
        ->and($event->sold_out)->toBeTrue()
        ->and($event->price)->toBe(9.5);
});

it('finds the schema from the post_type column, on a generic post model too', function (): void {
    $this->store->values['post:7:capacity'] = '12';

    expect(existingModel(Post::class, ['ID' => 7, 'post_type' => 'event'])->capacity)->toBe(12)
        ->and(existingModel(Post::class, ['ID' => 8, 'post_type' => 'page', 'capacity' => 'column'])->capacity)->toBe('column');
});

it('leaves undeclared attributes alone', function (): void {
    $event = existingModel(EventModel::class, ['ID' => 42, 'post_type' => 'event', 'post_title' => 'Concert']);

    expect($event->post_title)->toBe('Concert');
});

it('checks a write at once, and writes it through the meta API once the model is saved', function (): void {
    $event = existingModel(EventModel::class, ['ID' => 42, 'post_type' => 'event']);

    expect(fn (): string => $event->capacity = 'many')->toThrow(InvalidMetaValueException::class);

    $event->capacity = 300;

    expect($event->capacity)->toBe(300)
        ->and($event->hasPendingTypedMeta())->toBeTrue()
        ->and($event->getAttributes())->not->toHaveKey('capacity')
        ->and($this->store->values)->toBe([]);

    $event->saveTypedMeta();

    expect($this->store->values)->toBe(['post:42:capacity' => '300'])
        ->and($event->hasPendingTypedMeta())->toBeFalse()
        ->and($event->capacity)->toBe(300);
});

it('reads the default of a model not saved yet', function (): void {
    expect((new EventModel)->capacity)->toBe(0);
});

it('reads user meta on the user model', function (): void {
    $this->store->values['user:3:newsletter_opt_in'] = '1';

    expect(existingModel(User::class, ['ID' => 3])->newsletterOptIn)->toBeTrue();
});

it('stops eager loading the meta relation for a class known to carry typed meta only', function (): void {
    $with = new ReflectionProperty(Model::class, 'with');

    expect($with->getValue(new EventModel))->not->toContain('meta')
        ->and($with->getValue(new Post))->toContain('meta')
        ->and($with->getValue(new User))->not->toContain('meta');
});

it('primes the meta cache once for the models loaded together, on the first typed read', function (): void {
    Model::clearBootedModels();
    Model::setEventDispatcher(new Dispatcher);
    $this->store->values['post:1:capacity'] = '10';
    $this->store->values['post:2:capacity'] = '20';
    Functions\expect('update_meta_cache')->once()->with('post', [1, 2]);

    [$first, $second] = [(new EventModel)->newFromBuilder(['ID' => 1, 'post_type' => 'event']), (new EventModel)->newFromBuilder(['ID' => 2, 'post_type' => 'event'])];

    expect($first->capacity + $second->capacity)->toBe(30);

    Model::unsetEventDispatcher();
    Model::clearBootedModels();
});

it('does not prime the cache for models without typed meta', function (): void {
    Model::clearBootedModels();
    Model::setEventDispatcher(new Dispatcher);
    Functions\expect('update_meta_cache')->never();

    (new Post)->newFromBuilder(['ID' => 1, 'post_type' => 'page', 'post_title' => 'About'])->post_title;

    Model::unsetEventDispatcher();
    Model::clearBootedModels();
});

describe('whereMeta()', function (): void {
    beforeEach(function (): void {
        $connection = new MySqlConnection(fn (): never => throw new LogicException('No query should run.'), 'wordpress', 'wp_');
        $resolver = new ConnectionResolver(['wordpress' => $connection]);
        $resolver->setDefaultConnection('wordpress');
        Model::setConnectionResolver($resolver);
    });

    afterEach(function (): void {
        Model::unsetConnectionResolver();
    });

    it('compares numbers as numbers', function (): void {
        $query = EventModel::query()->whereMeta('capacity', '>=', 100);

        expect($query->toSql())->toContain('CAST(meta_value AS SIGNED) >= ?')
            ->and($query->getBindings())->toContain('capacity', 100);
    });

    it('compares other types as stored', function (): void {
        $query = EventModel::query()->whereMeta('soldOut', true);

        expect($query->toSql())->toContain('`meta_value` = ?')
            ->and($query->getBindings())->toContain('sold_out', '1');
    });

    it('matches an absent meta with null', function (): void {
        expect(EventModel::query()->whereMeta('subtitle', null)->toSql())->toContain('not exists');
    });

    it('refuses an undeclared meta or an unknown operator', function (): void {
        expect(fn () => EventModel::query()->whereMeta('nope', 1))->toThrow(InvalidArgumentException::class, 'has no typed meta named "nope"')
            ->and(fn () => EventModel::query()->whereMeta('capacity', 'like', 1))->toThrow(InvalidArgumentException::class, 'does not support the operator "like"');
    });
});
