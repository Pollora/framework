<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Infrastructure\Providers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\ThirdParty\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * WooCommerce service provider following hexagonal architecture.
 *
 * This service provider registers all WooCommerce-related services
 * and binds interfaces to their implementations according to the
 * hexagonal architecture pattern.
 */
class WooCommerceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDomainServices();
        $this->registerInfrastructureServices();
        $this->registerApplicationServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->executeWooCommerceHooksRegistration();
    }

    /**
     * Register domain layer services.
     */
    private function registerDomainServices(): void
    {
        $this->app->singleton(WooCommerceService::class);
    }

    /**
     * Register infrastructure layer services.
     */
    private function registerInfrastructureServices(): void
    {
        // Register the WordPress adapter
        $this->app->singleton(WordPressWooCommerceAdapter::class);

        // Register the main WooCommerce integration implementation
        $this->app->singleton(WooCommerceIntegrationInterface::class, fn ($app): \Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerce => new WooCommerce(
            $app->make(TemplateFinderInterface::class),
            $app->make(ViewFactory::class),
            $app->make(WooCommerceService::class),
            $app->make(WordPressWooCommerceAdapter::class)
        ));

        // Register the template resolver implementation
        $this->app->singleton(TemplateResolverInterface::class, fn ($app): \Pollora\ThirdParty\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver => new WooCommerceTemplateResolver(
            $app->make(WooCommerceService::class)
        ));

        // Maintain backward compatibility by binding the old class name
        $this->app->singleton(\Pollora\ThirdParty\WooCommerce\WooCommerce::class, fn ($app) => $app->make(WooCommerceIntegrationInterface::class));

        $this->app->singleton(\Pollora\ThirdParty\WooCommerce\View\WooCommerceTemplateResolver::class, fn ($app) => $app->make(TemplateResolverInterface::class));
    }

    /**
     * Register application layer services.
     */
    private function registerApplicationServices(): void
    {
        $this->app->singleton(RegisterWooCommerceHooksUseCase::class, fn ($app): \Pollora\ThirdParty\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase => new RegisterWooCommerceHooksUseCase(
            $app->make(Action::class),
            $app->make(Filter::class),
            $app->make(WooCommerceIntegrationInterface::class),
            $app->make(TemplateResolverInterface::class)
        ));
    }

    /**
     * Execute the WooCommerce hooks registration use case.
     */
    private function executeWooCommerceHooksRegistration(): void
    {
        $useCase = $this->app->make(RegisterWooCommerceHooksUseCase::class);
        $useCase->execute();
    }
}
