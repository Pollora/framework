<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\WpRest\Infrastructure\Providers\WpRestAttributeServiceProvider;
use Pollora\WpRest\Infrastructure\Services\WpRestDiscovery;

describe('WpRestAttributeServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $this->provider = new WpRestAttributeServiceProvider($this->container);
    });

    it('registers WpRestDiscovery as singleton', function (): void {
        $this->provider->register();

        expect($this->container->bound(WpRestDiscovery::class))->toBeTrue();

        $first = $this->container->make(WpRestDiscovery::class);
        $second = $this->container->make(WpRestDiscovery::class);
        expect($first)->toBe($second);
    });
});
