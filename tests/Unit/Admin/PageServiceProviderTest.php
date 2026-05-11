<?php

declare(strict_types=1);

use Pollora\Admin\PageServiceProvider;

describe('PageServiceProvider', function (): void {
    beforeEach(function (): void {
        $this->container = new PageTestContainer;
        $this->provider = new PageServiceProvider($this->container);
    });

    it('registers wp.admin.page as singleton', function (): void {
        $this->provider->register();

        expect($this->container->hasSingleton('wp.admin.page'))->toBeTrue();
    });

    it('registers a callable factory for wp.admin.page', function (): void {
        $this->provider->register();

        expect($this->container->getFactory('wp.admin.page'))->toBeCallable();
    });
});

class PageTestContainer extends TestContainer
{
    private array $singletons = [];

    public function singleton($abstract, $concrete = null): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function hasSingleton(string $abstract): bool
    {
        return isset($this->singletons[$abstract]);
    }

    public function getFactory(string $abstract): mixed
    {
        return $this->singletons[$abstract] ?? null;
    }
}
