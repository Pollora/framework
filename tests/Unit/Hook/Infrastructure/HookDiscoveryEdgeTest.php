<?php

declare(strict_types=1);

use Pollora\Attributes\Action as ActionAttribute;
use Pollora\Attributes\Filter as FilterAttribute;
use Pollora\Discovery\Domain\Models\DiscoveryLocation;
use Pollora\Discovery\Infrastructure\Services\ReflectionCache;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Infrastructure\Services\HookDiscovery;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredInterface;

abstract class EdgeAbstractHooks
{
    #[ActionAttribute('init')]
    public function boot(): void {}
}

final class EdgeBrokenFilterHooks
{
    public function __construct()
    {
        throw new RuntimeException('cannot build the hooks class');
    }

    #[FilterAttribute('the_title')]
    public function title(string $title): string
    {
        return $title;
    }
}

function edgeHookDiscovery(?LoggerInterface $logger = null): HookDiscovery
{
    return new HookDiscovery(Mockery::mock(Action::class), Mockery::mock(Filter::class), $logger);
}

function edgeDiscover(HookDiscovery $discovery, string $class, ?ReflectionCache $cache): void
{
    $discovery->discover(new DiscoveryLocation('', __DIR__), DiscoveredClass::fromReflection(new ReflectionClass($class)), $cache);
}

describe('HookDiscovery edge cases', function (): void {
    it('identifies itself as the hooks discovery', function (): void {
        expect(edgeHookDiscovery()->getIdentifier())->toBe('hooks');
    });

    it('collects nothing from a structure that is not a class', function (): void {
        $discovery = edgeHookDiscovery();

        $discovery->discover(new DiscoveryLocation('', __DIR__), new DiscoveredInterface('Thing', __FILE__, 'Tests', [], []), new ReflectionCache);

        expect(iterator_to_array($discovery->getItems()))->toBe([]);
    });

    it('collects nothing from an abstract class', function (): void {
        $discovery = edgeHookDiscovery();

        edgeDiscover($discovery, EdgeAbstractHooks::class, new ReflectionCache);

        expect(iterator_to_array($discovery->getItems()))->toBe([]);
    });

    it('skips a class it cannot reflect instead of failing the whole discovery', function (): void {
        $discovery = edgeHookDiscovery();

        edgeDiscover($discovery, EdgeBrokenFilterHooks::class, null);

        expect(iterator_to_array($discovery->getItems()))->toBe([]);
    });

    it('logs a hook class it cannot build and registers nothing for it', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(
            Mockery::on(fn (string $m): bool => str_contains($m, 'Failed to register filter hook from method') && str_contains($m, 'EdgeBrokenFilterHooks::title')),
            Mockery::on(fn (array $c): bool => $c['exception'] instanceof RuntimeException),
        );
        $discovery = edgeHookDiscovery($logger);
        edgeDiscover($discovery, EdgeBrokenFilterHooks::class, new ReflectionCache);

        $discovery->apply();
    });
});
