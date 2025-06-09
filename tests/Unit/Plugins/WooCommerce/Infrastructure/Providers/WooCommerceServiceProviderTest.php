<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Mockery\MockInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\Plugins\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\Plugins\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\Plugins\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\Plugins\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\Plugins\WooCommerce\Infrastructure\Providers\WooCommerceServiceProvider;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

describe('WooCommerceServiceProvider', function () {
    beforeEach(function () {
        setupWordPressMocks();

        $this->container = new WooCommerceTestContainer();
        $this->provider = new WooCommerceServiceProvider($this->container);
    });

    afterEach(function () {
        resetWordPressMocks();
        Mockery::close();
    });

    test('can register domain services', function () {
        $this->provider->register();

        expect($this->container->has(WooCommerceService::class))->toBeTrue();
    });

    test('can register infrastructure services', function () {
        // Mock required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));

        $this->provider->register();

        expect($this->container->has(WordPressWooCommerceAdapter::class))->toBeTrue();
        expect($this->container->has(WooCommerceIntegrationInterface::class))->toBeTrue();
        expect($this->container->has(TemplateResolverInterface::class))->toBeTrue();
    });

    test('can register application services', function () {
        // Mock required dependencies
        $this->container->instance(Action::class, Mockery::mock(Action::class));
        $this->container->instance(Filter::class, Mockery::mock(Filter::class));
        $this->container->instance(WooCommerceIntegrationInterface::class, Mockery::mock(WooCommerceIntegrationInterface::class));
        $this->container->instance(TemplateResolverInterface::class, Mockery::mock(TemplateResolverInterface::class));

        $this->provider->register();

        expect($this->container->has(RegisterWooCommerceHooksUseCase::class))->toBeTrue();
    });

    test('maintains backward compatibility bindings', function () {
        // Mock required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));

        $this->provider->register();

        expect($this->container->has(\Pollora\Plugins\WooCommerce\WooCommerce::class))->toBeTrue();
        expect($this->container->has(\Pollora\Plugins\WooCommerce\View\WooCommerceTemplateResolver::class))->toBeTrue();
    });

    test('can resolve woocommerce integration service', function () {
        // Mock all required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));
        $this->container->instance(WooCommerceService::class, new WooCommerceService());
        $this->container->instance(WordPressWooCommerceAdapter::class, new WordPressWooCommerceAdapter());

        $this->provider->register();

        $service = $this->container->get(WooCommerceIntegrationInterface::class);
        expect($service)->toBeInstanceOf(WooCommerce::class);
    });

    test('can resolve template resolver service', function () {
        // Mock all required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));
        $this->container->instance(WooCommerceService::class, new WooCommerceService());

        $this->provider->register();

        $service = $this->container->get(TemplateResolverInterface::class);
        expect($service)->toBeInstanceOf(WooCommerceTemplateResolver::class);
    });

    test('can resolve use case with all dependencies', function () {
        // Mock all required dependencies
        $this->container->instance(Action::class, Mockery::mock(Action::class));
        $this->container->instance(Filter::class, Mockery::mock(Filter::class));
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));
        $this->container->instance(WooCommerceService::class, new WooCommerceService());
        $this->container->instance(WordPressWooCommerceAdapter::class, new WordPressWooCommerceAdapter());

        $this->provider->register();

        $useCase = $this->container->get(RegisterWooCommerceHooksUseCase::class);
        expect($useCase)->toBeInstanceOf(RegisterWooCommerceHooksUseCase::class);
    });

    test('executes use case on boot', function () {
        // Create a mock use case that tracks execution
        $useCase = Mockery::mock(RegisterWooCommerceHooksUseCase::class);
        $useCase->shouldReceive('execute')->once();

        $this->container->instance(RegisterWooCommerceHooksUseCase::class, $useCase);

        $this->provider->boot();
    });
});

// Extend TestContainer to support singleton behavior for service provider tests
class WooCommerceTestContainer extends \TestContainer
{
    private array $singletons = [];

    public function singleton(string $abstract, $concrete = null): void
    {
        if ($concrete instanceof Closure) {
            $this->singletons[$abstract] = $concrete;
        } else {
            $this->services[$abstract] = $concrete;
        }
    }

    public function get(string $serviceClass): ?object
    {
        // Check if it's a singleton factory
        if (isset($this->singletons[$serviceClass])) {
            if (!isset($this->services[$serviceClass])) {
                $factory = $this->singletons[$serviceClass];
                $this->services[$serviceClass] = $factory($this);
            }
            return $this->services[$serviceClass];
        }

        return parent::get($serviceClass);
    }

    public function make(string $serviceClass): ?object
    {
        return $this->get($serviceClass);
    }
}