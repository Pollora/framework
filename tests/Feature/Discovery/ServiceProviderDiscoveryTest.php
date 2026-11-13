<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery\ServiceProviderFixtures;

use Illuminate\Support\ServiceProvider;
use Mockery;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ServiceProviderDiscovery;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredEnum;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

final class BookingServiceProvider extends ServiceProvider
{
    public static int $registered = 0;

    public function register(): void
    {
        self::$registered++;
    }
}

abstract class AbstractServiceProvider extends ServiceProvider {}

final class InheritingServiceProvider extends AbstractServiceProvider {}

final class NotAProvider {}

final class BrokenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new RuntimeException('cannot boot');
    }
}

function coveredStructure(string $class): DiscoveredClass
{
    return DiscoveredClass::fromReflection(new ReflectionClass($class));
}

function location(): DiscoveryLocation
{
    return new DiscoveryLocation('Tests\\', __DIR__);
}

function collectedClasses(ServiceProviderDiscovery $discovery): array
{
    return array_values(array_map(fn (array $item): string => $item['class'], iterator_to_array($discovery->getItems(), false)));
}

beforeEach(function (): void {
    BookingServiceProvider::$registered = 0;
});

it('identifies itself as the service provider discovery', function (): void {
    expect((new ServiceProviderDiscovery)->getIdentifier())->toBe('service_providers');
});

it('collects a concrete class that extends the service provider directly', function (): void {
    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(BookingServiceProvider::class));

    expect(collectedClasses($discovery))->toBe([BookingServiceProvider::class]);
});

it('collects a class that reaches the service provider through its parents', function (): void {
    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(InheritingServiceProvider::class));

    expect(collectedClasses($discovery))->toBe([InheritingServiceProvider::class]);
});

it('collects a class whose discovered data is incomplete by checking the class itself', function (): void {
    $structure = new DiscoveredClass(
        name: 'InheritingServiceProvider',
        file: __FILE__,
        namespace: __NAMESPACE__,
        isFinal: true,
        isAbstract: false,
        isReadonly: false,
        extends: AbstractServiceProvider::class,
        implements: [],
        attributes: [],
        extendsChain: null,
    );

    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), $structure);

    expect(collectedClasses($discovery))->toBe([InheritingServiceProvider::class]);
});

it('ignores abstract providers, classes that are not providers and unloadable classes', function (): void {
    $unloadable = new DiscoveredClass(
        name: 'GhostServiceProvider',
        file: __FILE__,
        namespace: __NAMESPACE__,
        isFinal: false,
        isAbstract: false,
        isReadonly: false,
        extends: 'Missing\\Parent',
        implements: [],
        attributes: [],
        extendsChain: [],
    );

    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(AbstractServiceProvider::class));
    $discovery->discover(location(), coveredStructure(NotAProvider::class));
    $discovery->discover(location(), $unloadable);

    expect(collectedClasses($discovery))->toBe([]);
});

it('ignores structures that are not classes', function (): void {
    $interface = Mockery::mock(DiscoveredInterface::class);
    $enum = Mockery::mock(DiscoveredEnum::class);

    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), $interface);
    $discovery->discover(location(), $enum);

    expect(collectedClasses($discovery))->toBe([]);
});

it('registers every discovered provider with the application', function (): void {
    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(BookingServiceProvider::class));
    $discovery->discover(location(), coveredStructure(InheritingServiceProvider::class));

    $discovery->apply();

    expect($this->app->getLoadedProviders())
        ->toHaveKey(BookingServiceProvider::class)
        ->toHaveKey(InheritingServiceProvider::class)
        ->and(BookingServiceProvider::$registered)->toBe(1);
});

it('does not register a provider twice', function (): void {
    $this->app->register(BookingServiceProvider::class);

    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(BookingServiceProvider::class));
    $discovery->apply();

    expect(BookingServiceProvider::$registered)->toBe(1);
});

it('logs a provider that fails to register and carries on with the next one', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Failed to register service provider '.BrokenServiceProvider::class
            && $context['exception']->getMessage() === 'cannot boot'
    );
    $this->app->instance(LoggerInterface::class, $logger);

    $discovery = new ServiceProviderDiscovery;
    $discovery->discover(location(), coveredStructure(BrokenServiceProvider::class));
    $discovery->discover(location(), coveredStructure(BookingServiceProvider::class));

    $discovery->apply();

    expect(BookingServiceProvider::$registered)->toBe(1);
});
