<?php

declare(strict_types=1);

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Config\Infrastructure\Services\LaravelConfigRepository;

describe('ConfigServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->container = new ConfigTestContainer;
        $this->provider = new ConfigServiceProvider($this->container);
    });

    test('binds ConfigRepositoryInterface to LaravelConfigRepository', function (): void {
        $this->provider->register();

        expect($this->container->has(ConfigRepositoryInterface::class))->toBeTrue();
    });

    test('resolves to LaravelConfigRepository instance', function (): void {
        $this->provider->register();

        $resolved = $this->container->get(ConfigRepositoryInterface::class);
        expect($resolved)->toBeInstanceOf(LaravelConfigRepository::class);
    });
});

class ConfigTestContainer extends TestContainer
{
    private array $bindings = [];

    public function bind($abstract, $concrete = null, $shared = false): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function has(string $serviceClass): bool
    {
        return isset($this->bindings[$serviceClass]) || parent::has($serviceClass);
    }

    public function get(string $serviceClass): ?object
    {
        if (isset($this->bindings[$serviceClass])) {
            $concrete = $this->bindings[$serviceClass];

            return is_string($concrete) ? new $concrete : $concrete;
        }

        return parent::get($serviceClass);
    }

    public function make($abstract, array $parameters = []): ?object
    {
        if (is_string($abstract)) {
            return $this->get($abstract);
        }

        return null;
    }
}
