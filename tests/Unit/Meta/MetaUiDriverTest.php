<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Meta\Application\Services\MetaSchemaBuilder;
use Pollora\Meta\Application\Services\MetaSchemaRepository;
use Pollora\Meta\Application\Services\MetaUiDrivers;
use Pollora\Meta\Domain\Contracts\MetaUiDriver;
use Pollora\Meta\Domain\Enums\Control;
use Pollora\Meta\Domain\Events\MetaSchemasRegistered;
use Pollora\Meta\Domain\Models\MetaDefinition;
use Pollora\Meta\Domain\Models\MetaSchema;
use Pollora\Meta\Infrastructure\Laravel\MetaServiceProvider;
use Psr\Log\LoggerInterface;
use Tests\Unit\Meta\Fixtures\Event;

/**
 * A driver that "builds" a field per meta it supports, the way an ACF or Meta
 * Box driver would, and records them.
 */
final class FieldRecordingDriver implements MetaUiDriver
{
    /** @var array<string, array{control: string|null, label: string|null, group: string|null, hints: array<string, mixed>}> */
    public array $fields = [];

    public function supports(MetaDefinition $definition): bool
    {
        return ! $definition->isStructured();
    }

    public function register(MetaSchema $schema): void
    {
        foreach ($schema->definitions as $definition) {
            $this->fields[$definition->key] = [
                'control' => $definition->control?->value,
                'label' => $definition->label,
                'group' => $definition->group,
                'hints' => $definition->hints['recording'] ?? [],
            ];
        }
    }
}

describe('on init', function (): void {
    beforeEach(function (): void {
        $this->app = new Application(sys_get_temp_dir());
        $this->app->instance('config', new Repository(['app' => ['debug' => false]]));
        $this->app->instance('events', $this->events = new Dispatcher($this->app));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en')));
        $this->app->instance(LoggerInterface::class, Mockery::mock(LoggerInterface::class));

        $this->action = Mockery::mock(Action::class);
        $this->app->instance(Action::class, $this->action);
        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnSelf();
        $this->app->instance(Filter::class, $filter);
        $this->provider = new MetaServiceProvider($this->app);
        $this->provider->register();
        $this->app->make(MetaSchemaRepository::class)->add((new MetaSchemaBuilder)->build(Event::class));
    });

    it('announces the schemas after registering them, and hands them to the configured driver', function (): void {
        $announce = null;
        $this->action->shouldReceive('add')->once()->with('init', Mockery::type(Closure::class), 21)->andReturnUsing(function (string $hook, Closure $callback) use (&$announce): Action {
            $announce = $callback;

            return $this->action;
        });
        $dispatched = [];
        $this->events->listen(MetaSchemasRegistered::class, function (MetaSchemasRegistered $event) use (&$dispatched): void {
            $dispatched = $event->schemas;
        });
        $driver = new FieldRecordingDriver;
        $this->app->make('config')->set('meta.ui', 'recording');
        $this->app->make(MetaUiDrivers::class)->extend('recording', static fn (): MetaUiDriver => $driver);

        $this->provider->boot();
        $announce();

        expect($dispatched)->toHaveCount(1)
            ->and($driver->fields)->toHaveKey('capacity');
    });

    it('generates no field without a driver', function (): void {
        expect($this->app->make('config')->get('meta.ui'))->toBeNull();
    });
});
