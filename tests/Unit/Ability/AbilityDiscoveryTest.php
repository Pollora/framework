<?php

declare(strict_types=1);

use Pollora\Abilities\Application\Service\RegisterAbilityService;
use Pollora\Abilities\Domain\Contracts\AbilityHandler;
use Pollora\Abilities\Domain\Model\AbilityCategory;
use Pollora\Abilities\Domain\Model\Behaviour;
use Pollora\Abilities\Domain\Model\Input;
use Pollora\Abilities\Domain\Schema\SchemaBuilder;
use Pollora\Abilities\Factory\AbilityFactory;
use Pollora\Abilities\Port\Out\AbilityCategoryRegistrarPort;
use Pollora\Abilities\Port\Out\AbilityRegistrarPort;
use Pollora\Ability\Infrastructure\Services\AbilityDiscovery;
use Pollora\Attributes\Ability;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

#[Ability(name: 'fixture/read-things', description: 'Reads things', category: 'fixture-things', label: 'Read things')]
final class AbilityDiscoveryHandlerFixture implements AbilityHandler
{
    public function schema(SchemaBuilder $schema): void {}

    public function authorize(Input $input): mixed
    {
        return true;
    }

    public function handle(Input $input): mixed
    {
        return [];
    }
}

#[Ability(name: 'fixture/delete-things', description: 'Deletes things', category: 'fixture-things', behaviour: Behaviour::Deletes)]
final class AbilityDiscoveryDeleterFixture implements AbilityHandler
{
    public function schema(SchemaBuilder $schema): void {}

    public function authorize(Input $input): mixed
    {
        return true;
    }

    public function handle(Input $input): mixed
    {
        return [];
    }
}

#[Ability(name: 'fixture/not-a-handler', description: 'Forgot the interface', category: 'fixture-things')]
final class AbilityDiscoveryNotAHandlerFixture {}

final class AbilityDiscoveryPlainFixture {}

#[Ability(name: 'fixture/abstract', description: 'Abstract', category: 'fixture-things')]
abstract class AbilityDiscoveryAbstractFixture {}

/**
 * @return array{0: AbilityDiscovery, 1: RegisterAbilityService, 2: object}
 */
function abilityDiscoveryOnService(?LoggerInterface $logger = null, bool $categoryExists = false): array
{
    $abilityRegistrar = new class implements AbilityRegistrarPort
    {
        public function register(Pollora\Abilities\Domain\Model\Ability $ability): bool
        {
            return true;
        }

        public function exists(string $name): bool
        {
            return false;
        }
    };

    $categoryRegistrar = new readonly class($categoryExists) implements AbilityCategoryRegistrarPort
    {
        public function __construct(private bool $exists) {}

        public function register(AbilityCategory $category): bool
        {
            return true;
        }

        public function exists(string $slug): bool
        {
            return $this->exists;
        }
    };

    $service = new RegisterAbilityService($abilityRegistrar, $categoryRegistrar);

    return [new AbilityDiscovery(new AbilityFactory($service), $logger), $service, $categoryRegistrar];
}

function discoverAbilityClass(AbilityDiscovery $discovery, string $class): void
{
    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        DiscoveredClass::fromReflection(new ReflectionClass($class)),
        new ReflectionCache
    );
}

it('identifies itself as the ability discovery', function (): void {
    [$discovery] = abilityDiscoveryOnService();

    expect($discovery->getIdentifier())->toBe('ability');
});

it('queues each decorated handler on the factory without publishing it', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    discoverAbilityClass($discovery, AbilityDiscoveryHandlerFixture::class);
    discoverAbilityClass($discovery, AbilityDiscoveryDeleterFixture::class);
    $discovery->apply();

    $pending = $service->pending();

    expect(array_map(fn (Pollora\Abilities\Domain\Model\Ability $ability): string => $ability->name, $pending))->toBe(['fixture/read-things', 'fixture/delete-things'])
        ->and($pending[0]->label)->toBe('Read things')
        ->and($pending[0]->description)->toBe('Reads things')
        ->and($pending[0]->category)->toBe('fixture-things')
        ->and($service->registered())->toBe([]);
});

it('carries the behaviour declared by the attribute into the queued ability', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    discoverAbilityClass($discovery, AbilityDiscoveryDeleterFixture::class);
    discoverAbilityClass($discovery, AbilityDiscoveryHandlerFixture::class);
    $discovery->apply();

    [$deleter, $reader] = $service->pending();

    expect($deleter->annotations->readonly)->toBeFalse()
        ->and($deleter->annotations->destructive)->toBeTrue()
        ->and($reader->annotations->readonly)->toBeTrue();
});

it('declares the category named by the attribute so the attribute alone is enough', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    discoverAbilityClass($discovery, AbilityDiscoveryHandlerFixture::class);
    $discovery->apply();

    expect(array_map(fn (AbilityCategory $category): string => $category->slug, $service->pendingCategories()))->toBe(['fixture-things']);
});

it('does not redeclare a category that WordPress already knows', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService(categoryExists: true);

    discoverAbilityClass($discovery, AbilityDiscoveryHandlerFixture::class);
    $discovery->apply();

    expect($service->pendingCategories())->toBe([])
        ->and($service->pending())->toHaveCount(1);
});

it('reports a decorated class that does not implement the handler contract, and queues nothing for it', function (): void {
    $logged = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->andReturnUsing(function (string $message) use (&$logged): void {
        $logged[] = $message;
    });

    [$discovery, $service] = abilityDiscoveryOnService($logger);

    discoverAbilityClass($discovery, AbilityDiscoveryNotAHandlerFixture::class);
    discoverAbilityClass($discovery, AbilityDiscoveryHandlerFixture::class);
    $discovery->apply();

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toContain(AbilityDiscoveryNotAHandlerFixture::class)
        ->and($logged[0])->toContain('fixture/not-a-handler')
        ->and(array_map(fn (Pollora\Abilities\Domain\Model\Ability $ability): string => $ability->name, $service->pending()))->toBe(['fixture/read-things']);
});

it('ignores classes without the attribute', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    discoverAbilityClass($discovery, AbilityDiscoveryPlainFixture::class);
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($service->pending())->toBe([]);
});

it('ignores abstract classes even when they carry the attribute', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    discoverAbilityClass($discovery, AbilityDiscoveryAbstractFixture::class);
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($service->pending())->toBe([]);
});

it('ignores structures that are not classes', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        DiscoveredInterface::fromReflection(new ReflectionClass(Countable::class)),
        new ReflectionCache
    );
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($service->pending())->toBe([]);
});

it('does not fail on a class that cannot be reflected', function (): void {
    [$discovery, $service] = abilityDiscoveryOnService();

    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        new DiscoveredClass('Missing', __FILE__, 'Not\\Existing', false, false, false, null, [], []),
        new ReflectionCache
    );
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($service->pending())->toBe([]);
});

it('reports an ability it could not build and goes on with the others', function (): void {
    $logged = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->andReturnUsing(function (string $message) use (&$logged): void {
        $logged[] = $message;
    });

    [$discovery, $service] = abilityDiscoveryOnService($logger);
    $location = new DiscoveryLocation('Tests\\', __DIR__);
    $attribute = (new ReflectionClass(AbilityDiscoveryHandlerFixture::class))->getAttributes(Ability::class)[0];

    $discovery->getItems()->add($location, ['class' => 'Not\\Existing\\Handler', 'attribute' => $attribute]);
    $discovery->getItems()->add($location, ['class' => AbilityDiscoveryHandlerFixture::class, 'attribute' => $attribute]);

    $discovery->apply();

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toContain('Failed to register ability from Not\\Existing\\Handler')
        ->and(array_map(fn (Pollora\Abilities\Domain\Model\Ability $ability): string => $ability->name, $service->pending()))->toBe(['fixture/read-things']);
});
