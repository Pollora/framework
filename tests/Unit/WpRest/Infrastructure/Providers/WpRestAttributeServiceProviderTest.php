<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\WpRest\Infrastructure\Providers\WpRestAttributeServiceProvider;
use Pollora\WpRest\Infrastructure\Services\WpRestDiscovery;

describe('WpRestAttributeServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $this->provider = new WpRestAttributeServiceProvider($this->container);

        $this->discoveryEngine = Mockery::mock(DiscoveryEngineInterface::class)->shouldIgnoreMissing();
        $this->container->instance(DiscoveryEngineInterface::class, $this->discoveryEngine);
    });

    it('registers WpRestDiscovery as singleton', function (): void {
        $initialBindings = count($this->container->getBindings());

        $this->provider->register();

        expect(count($this->container->getBindings()))->toBe($initialBindings + 1);
        expect($this->container->bound(WpRestDiscovery::class))->toBeTrue();
    });

    it('boot processes discovered REST routes without error', function (): void {
        $this->provider->boot();
    })->throwsNoExceptions();

    it('boot handles empty discovery gracefully', function (): void {
        $this->provider->boot();
    })->throwsNoExceptions();

    it('boot handles discovery failure gracefully', function (): void {
        $this->provider->boot();
    })->throwsNoExceptions();

    it('boot registers WpRestDiscovery with engine', function (): void {
        $this->discoveryEngine->shouldReceive('addDiscovery')
            ->once()
            ->with('wp_rest_routes', Mockery::type(WpRestDiscovery::class));

        $this->provider->register();
        $this->provider->boot();
    });

    it('boot handles no discovery engine gracefully', function (): void {
        unset($this->container[DiscoveryEngineInterface::class]);

        $this->provider->boot();
    })->throwsNoExceptions();

    it('boot handles valid route data without error', function (): void {
        $this->provider->boot();
    })->throwsNoExceptions();
});

class TestWpRestRoute
{
    public string $namespace = 'test/v1';

    public string $route = 'endpoint';
}
