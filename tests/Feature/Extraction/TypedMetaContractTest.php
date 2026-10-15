<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Domain\Contracts\MetaStoreInterface;
use Pollora\Meta\Domain\Contracts\MetaUiDriver;
use Pollora\Meta\Domain\Enums\MetaObjectType;
use Pollora\Meta\Domain\Exceptions\MetaValidationException;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaRecord;
use Pollora\Meta\Domain\Models\MetaSchema;
use Pollora\Meta\Infrastructure\Providers\MetaServiceProvider;
use Pollora\Support\Facades\Meta;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Unit\Meta\Fixtures\Event;
use Tests\Unit\Meta\Fixtures\EventStatus;
use Tests\Unit\Meta\Fixtures\MemberProfile;
use Tests\Unit\Meta\Fixtures\RatedEvent;

/**
 * Typed meta end to end, with the real provider, the real Laravel container and validator, and a real store
 * that keeps its rows in memory (WordPress's meta API is the only thing replaced). It freezes what the
 * extraction of `pollora/meta` must keep: declaring with attributes, reading and writing through the fluent
 * `Meta::of()` record, the rules, the schemas given to tooling and the UI drivers.
 */
final class MemoryMetaStore implements MetaStoreInterface
{
    /** @var array<string, mixed> */
    public array $rows = [];

    public function get(MetaObjectType $objectType, int $objectId, string $key): mixed
    {
        return $this->rows[$this->id($objectType, $objectId, $key)] ?? '';
    }

    public function getAll(MetaObjectType $objectType, int $objectId, string $key): array
    {
        return (array) ($this->rows[$this->id($objectType, $objectId, $key)] ?? []);
    }

    public function update(MetaObjectType $objectType, int $objectId, string $key, string|array $value): void
    {
        $this->rows[$this->id($objectType, $objectId, $key)] = $value;
    }

    public function replaceAll(MetaObjectType $objectType, int $objectId, string $key, array $values): void
    {
        $this->rows[$this->id($objectType, $objectId, $key)] = $values;
    }

    public function delete(MetaObjectType $objectType, int $objectId, string $key): void
    {
        unset($this->rows[$this->id($objectType, $objectId, $key)]);
    }

    private function id(MetaObjectType $objectType, int $objectId, string $key): string
    {
        return $objectType->value.':'.$objectId.':'.$key;
    }
}

final class RecordingUiDriver implements MetaUiDriver
{
    /** @var list<string> */
    public array $registered = [];

    public function supports(MetaDefinition $definition): bool
    {
        return true;
    }

    public function register(MetaSchema $schema): void
    {
        foreach ($schema->definitions as $definition) {
            $this->registered[] = implode(',', $schema->subtypes).'.'.$definition->key;
        }
    }
}

beforeEach(function (): void {
    $this->app = new Application(sys_get_temp_dir());
    $this->app->instance('config', new Repository(['app' => ['debug' => false]]));
    $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en')));
    $this->app->instance(LoggerInterface::class, new NullLogger);

    $this->hooks = [];
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->andReturnUsing(function (string $hook, Closure $callback) use ($action): Action {
        $this->hooks[$hook] = $callback;

        return $action;
    });
    $this->app->instance(Action::class, $action);
    $this->app->instance(Filter::class, Mockery::mock(Filter::class)->shouldIgnoreMissing());
    $this->app->instance('events', new Dispatcher);

    $this->provider = new MetaServiceProvider($this->app);
    $this->provider->register();

    $this->store = new MemoryMetaStore;
    $this->app->instance(MetaStoreInterface::class, $this->store);

    // What the discovery does at boot: one compiled schema per declaring class
    $builder = $this->app->make(MetaSchemaBuilder::class);
    $repository = $this->app->make(MetaSchemaRepository::class);
    foreach ([Event::class, MemberProfile::class, RatedEvent::class] as $class) {
        $repository->add($builder->build($class));
    }

    Facade::setFacadeApplication($this->app);
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('reads the declared defaults, typed, when nothing is stored', function (): void {
    $event = Meta::of(Event::class, 42);

    expect($event)->toBeInstanceOf(MetaRecord::class)
        ->and($event->objectId())->toBe(42)
        ->and($event->capacity)->toBe(0)
        ->and($event->price)->toBe(9.5)
        ->and($event->soldOut)->toBeFalse()
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->startsAt)->toBeNull();
});

it('writes through the fluent record under the declared key, and reads back the PHP type', function (): void {
    $saved = Meta::of(Event::class, 42)->fill(['capacity' => 250, 'soldOut' => true, 'status' => EventStatus::Published, 'internalRef' => 'X-1'])->save();

    expect($saved)->toBeInstanceOf(MetaRecord::class)
        ->and($this->store->rows)->toHaveKeys(['post:42:capacity', 'post:42:sold_out', 'post:42:status', 'post:42:_event_internal_ref']);

    $again = Meta::of(Event::class, 42);
    expect($again->capacity)->toBe(250)
        ->and($again->soldOut)->toBeTrue()
        ->and($again->status)->toBe(EventStatus::Published)
        ->and($again->internalRef)->toBe('X-1')
        ->and($again->toArray())->toMatchArray(['capacity' => 250, 'soldOut' => true]);
});

it('chains set() and keeps the record', function (): void {
    $record = Meta::of(Event::class, 7);

    expect($record->set('capacity', 12))->toBe($record)
        ->and($record->get('capacity'))->toBe(12);
});

it('stores the meta of users, terms and comments under their own object type', function (): void {
    Meta::of(MemberProfile::class, 5)->fill(['newsletterOptIn' => true, 'jobTitle' => 'Chef'])->save();

    expect($this->store->rows)->toHaveKeys(['user:5:newsletter_opt_in', 'user:5:job_title'])
        ->and(Meta::of(MemberProfile::class, 5)->newsletterOptIn)->toBeTrue();
});

it('applies the Laravel rules to writes from PHP, with the message of the rule', function (): void {
    $event = Meta::of(RatedEvent::class, 9);

    expect(fn () => $event->set('capacity', 9000))->toThrow(MetaValidationException::class)
        ->and(fn () => $event->set('contact', 'not-an-email'))->toThrow(MetaValidationException::class)
        ->and(fn () => $event->set('status', EventStatus::Draft))->toThrow(MetaValidationException::class);

    $event->set('capacity', 100)->set('contact', 'a@b.fr');
    expect($event->capacity)->toBe(100);
});

it('gives the compiled schemas to tooling, by object type and subtype', function (): void {
    $schemas = Meta::schemaFor('post', 'event');

    expect($schemas)->toHaveCount(1)
        ->and($schemas[0]->subtypes)->toBe(['event'])
        ->and(array_map(fn (MetaDefinition $d): string => $d->key, $schemas[0]->definitions))
        ->toContain('capacity', 'sold_out', 'starts_at', 'status', '_event_internal_ref');
});

it('hands the schemas to the UI driver picked in meta.ui, once WordPress has registered them', function (): void {
    $driver = new RecordingUiDriver;
    $this->app->make('config')->set('meta.ui', 'recording');
    Meta::extend('recording', fn (): RecordingUiDriver => $driver);

    $this->provider->boot();
    ($this->hooks['init'])();

    expect($driver->registered)->toContain('event.capacity', 'event.sold_out', 'rated_event.contact', '.newsletter_opt_in');
});
