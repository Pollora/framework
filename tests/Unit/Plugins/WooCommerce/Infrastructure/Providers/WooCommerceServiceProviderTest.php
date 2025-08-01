<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\ThirdParty\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Providers\WooCommerceServiceProvider;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

describe('WooCommerceServiceProvider', function () {
    beforeEach(function () {
        setupWordPressMocks();

        $this->container = new WooCommerceTestContainer;
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

        expect($this->container->has(\Pollora\ThirdParty\WooCommerce\WooCommerce::class))->toBeTrue();
        expect($this->container->has(\Pollora\ThirdParty\WooCommerce\View\WooCommerceTemplateResolver::class))->toBeTrue();
    });

    test('can resolve woocommerce integration service', function () {
        // Mock all required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));
        $this->container->instance(WooCommerceService::class, new WooCommerceService);
        $this->container->instance(WordPressWooCommerceAdapter::class, new WordPressWooCommerceAdapter);

        $this->provider->register();

        $service = $this->container->get(WooCommerceIntegrationInterface::class);
        expect($service)->toBeInstanceOf(WooCommerce::class);
    });

    test('can resolve template resolver service', function () {
        // Mock all required dependencies
        $this->container->instance(TemplateFinderInterface::class, Mockery::mock(TemplateFinderInterface::class));
        $this->container->instance(ViewFactory::class, Mockery::mock(ViewFactory::class));
        $this->container->instance(WooCommerceService::class, new WooCommerceService);

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
        $this->container->instance(WooCommerceService::class, new WooCommerceService);
        $this->container->instance(WordPressWooCommerceAdapter::class, new WordPressWooCommerceAdapter);

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
class WooCommerceTestContainer extends \TestContainer implements \Illuminate\Contracts\Container\Container
{
    private array $singletons = [];

    private array $services = [];

    public function singleton($abstract, $concrete = null)
    {
        if ($concrete instanceof \Closure) {
            $this->singletons[$abstract] = $concrete;
        } elseif ($concrete === null) {
            // When no concrete is provided, Laravel auto-resolves the class
            $this->singletons[$abstract] = function ($container) use ($abstract) {
                return new $abstract;
            };
        } else {
            $this->services[$abstract] = $concrete;
        }
    }

    public function get(string $serviceClass): ?object
    {
        // Check if it's a singleton factory
        if (isset($this->singletons[$serviceClass])) {
            if (! isset($this->services[$serviceClass])) {
                $factory = $this->singletons[$serviceClass];
                $this->services[$serviceClass] = $factory($this);
            }

            return $this->services[$serviceClass];
        }

        return parent::get($serviceClass);
    }

    public function make($abstract, array $parameters = [])
    {
        // Support both Laravel Container interface (mixed $abstract, array $parameters)
        // and TestContainer interface (string $serviceClass)
        if (is_string($abstract)) {
            return $this->get($abstract);
        }

        // Handle other types if needed
        return null;
    }

    public function has(string $serviceClass): bool
    {
        return isset($this->services[$serviceClass]) || isset($this->singletons[$serviceClass]);
    }

    // Required by Container interface
    public function bound($abstract): bool
    {
        return $this->has($abstract);
    }

    public function alias($abstract, $alias): void
    {
        // Simplified implementation
    }

    public function tag($abstracts, $tags): void
    {
        // Simplified implementation
    }

    public function tagged($tag): iterable
    {
        return [];
    }

    public function bind($abstract, $concrete = null, $shared = false): void
    {
        if ($shared) {
            $this->singleton($abstract, $concrete);
        } else {
            $this->services[$abstract] = $concrete;
        }
    }

    public function bindIf($abstract, $concrete = null, $shared = false): void
    {
        if (! $this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    public function scoped($abstract, $concrete = null): void
    {
        $this->singleton($abstract, $concrete);
    }

    public function scopedIf($abstract, $concrete = null): void
    {
        if (! $this->bound($abstract)) {
            $this->scoped($abstract, $concrete);
        }
    }

    public function singletonIf($abstract, $concrete = null): void
    {
        if (! $this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    public function extend($abstract, \Closure $closure): void
    {
        // Simplified implementation
    }

    public function when($concrete): \Illuminate\Contracts\Container\ContextualBindingBuilder
    {
        throw new \Exception('when() not implemented in test container');
    }

    public function factory($abstract): \Closure
    {
        return function () use ($abstract) {
            return $this->make($abstract);
        };
    }

    public function flush(): void
    {
        $this->services = [];
        $this->singletons = [];
    }

    public function resolved($abstract): bool
    {
        return isset($this->services[$abstract]);
    }

    public function resolving($abstract, ?\Closure $callback = null): void
    {
        // Simplified implementation
    }

    public function afterResolving($abstract, ?\Closure $callback = null): void
    {
        // Simplified implementation
    }

    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        // Simplified implementation
        if (is_callable($callback)) {
            return call_user_func_array($callback, $parameters);
        }
        throw new \Exception('call() not fully implemented in test container');
    }

    public function bindMethod($method, $callback)
    {
        // Simplified implementation
    }

    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        // Simplified implementation
    }

    public function beforeResolving($abstract, $callback = null)
    {
        // Simplified implementation
    }
}
