<?php

declare(strict_types=1);

use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAccess;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Infrastructure\Services\AjaxDiscovery;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;
use Pollora\Attributes\Ajax;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

final class AjaxDiscoveryFixture
{
    #[Ajax('fixture_default')]
    public function byDefault(): string
    {
        return 'default';
    }

    #[Ajax('fixture_guest', AjaxAccess::GUEST)]
    public function forGuests(): string
    {
        return 'guests';
    }

    #[Ajax('fixture_all', access: AjaxAccess::ALL)]
    public function forEveryone(): string
    {
        return 'everyone';
    }

    public function notAnAction(): string
    {
        return $this->hidden();
    }

    #[Ajax('fixture_private')]
    private function hidden(): string
    {
        return 'hidden';
    }
}

abstract class AjaxDiscoveryAbstractFixture
{
    #[Ajax('fixture_abstract')]
    public function action(): string
    {
        return 'abstract';
    }
}

/**
 * @return array{0: AjaxDiscovery, 1: ArrayObject<int, array{string, string}>}
 */
function ajaxDiscoveryWithRecorder(?LoggerInterface $logger = null): array
{
    $registered = new ArrayObject;
    $registrar = new class($registered) implements AjaxActionRegistrarPort
    {
        public function __construct(private ArrayObject $registered) {}

        public function register(AjaxAction $action): void
        {
            $this->registered[] = [$action->getName(), $action->getUserType(), $action->getCallback()];
        }
    };

    $factory = new AjaxFactory(new RegisterAjaxActionService($registrar));

    return [new AjaxDiscovery($factory, $logger), $registered];
}

function discoverAjaxClass(AjaxDiscovery $discovery, string $class): void
{
    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        DiscoveredClass::fromReflection(new ReflectionClass($class)),
        new ReflectionCache
    );
}

it('identifies itself as the ajax discovery', function (): void {
    [$discovery] = ajaxDiscoveryWithRecorder();

    expect($discovery->getIdentifier())->toBe('ajax');
});

it('registers every public method carrying #[Ajax] with the access its attribute declares', function (): void {
    [$discovery, $registered] = ajaxDiscoveryWithRecorder();

    discoverAjaxClass($discovery, AjaxDiscoveryFixture::class);
    $discovery->apply();

    $byName = [];
    foreach ($registered as [$name, $userType, $callback]) {
        $byName[$name] = [$userType, $callback[1]];
    }

    expect($byName)->toBe([
        'fixture_default' => [AjaxAction::LOGGED_USERS, 'byDefault'],
        'fixture_guest' => [AjaxAction::GUEST_USERS, 'forGuests'],
        'fixture_all' => [AjaxAction::BOTH_USERS, 'forEveryone'],
    ]);
});

it('hands the action an instance of the declaring class as its callback', function (): void {
    [$discovery, $registered] = ajaxDiscoveryWithRecorder();

    discoverAjaxClass($discovery, AjaxDiscoveryFixture::class);
    $discovery->apply();

    expect($registered[0][2][0])->toBeInstanceOf(AjaxDiscoveryFixture::class);
});

it('ignores abstract classes', function (): void {
    [$discovery, $registered] = ajaxDiscoveryWithRecorder();

    discoverAjaxClass($discovery, AjaxDiscoveryAbstractFixture::class);
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($registered)->toHaveCount(0);
});

it('ignores structures that are not classes', function (): void {
    [$discovery, $registered] = ajaxDiscoveryWithRecorder();

    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        DiscoveredInterface::fromReflection(new ReflectionClass(Countable::class)),
        new ReflectionCache
    );
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($registered)->toHaveCount(0);
});

it('does not fail on a class that cannot be reflected', function (): void {
    [$discovery, $registered] = ajaxDiscoveryWithRecorder();

    $discovery->discover(
        new DiscoveryLocation('Tests\\', __DIR__),
        new DiscoveredClass('Missing', __FILE__, 'Not\\Existing', false, false, false, null, [], []),
        new ReflectionCache
    );
    expect($discovery->getItems()->count())->toBe(0);

    $discovery->apply();

    expect($registered)->toHaveCount(0);
});

it('reports an action it could not register and goes on with the others', function (): void {
    $logged = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->andReturnUsing(function (string $message) use (&$logged): void {
        $logged[] = $message;
    });

    [$discovery, $registered] = ajaxDiscoveryWithRecorder($logger);

    // The middle item points at a class that cannot be built; the others must still be registered.
    $discovery->getItems()->add(new DiscoveryLocation('Tests\\', __DIR__), [
        'class' => AjaxDiscoveryFixture::class,
        'method' => 'byDefault',
        'attribute' => (new ReflectionMethod(AjaxDiscoveryFixture::class, 'byDefault'))->getAttributes(Ajax::class)[0],
    ]);
    $discovery->getItems()->add(new DiscoveryLocation('Tests\\', __DIR__), [
        'class' => 'Not\\Existing\\Handler',
        'method' => 'run',
        'attribute' => (new ReflectionMethod(AjaxDiscoveryFixture::class, 'byDefault'))->getAttributes(Ajax::class)[0],
    ]);
    $discovery->getItems()->add(new DiscoveryLocation('Tests\\', __DIR__), [
        'class' => AjaxDiscoveryFixture::class,
        'method' => 'forGuests',
        'attribute' => (new ReflectionMethod(AjaxDiscoveryFixture::class, 'forGuests'))->getAttributes(Ajax::class)[0],
    ]);

    $discovery->apply();

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toContain('Failed to register Ajax action')
        ->and(array_column($registered->getArrayCopy(), 0))->toBe(['fixture_default', 'fixture_guest']);
});
