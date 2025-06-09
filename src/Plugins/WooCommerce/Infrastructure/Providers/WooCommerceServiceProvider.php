<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\Infrastructure\Providers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Plugins\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\Plugins\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\Plugins\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\Plugins\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\Plugins\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\Plugins\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;
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
        $this->app->singleton(WooCommerceIntegrationInterface::class, function ($app) {
            return new WooCommerce(
                $app,
                $app->make(TemplateFinderInterface::class),
                $app->make(ViewFactory::class),
                $app->make(WooCommerceService::class),
                $app->make(WordPressWooCommerceAdapter::class)
            );
        });

        // Register the template resolver implementation
        $this->app->singleton(TemplateResolverInterface::class, function ($app) {
            return new WooCommerceTemplateResolver(
                $app->make(TemplateFinderInterface::class),
                $app->make(ViewFactory::class),
                $app->make(WooCommerceService::class)
            );
        });

        // Maintain backward compatibility by binding the old class name
        $this->app->singleton(\Pollora\Plugins\WooCommerce\WooCommerce::class, function ($app) {
            return $app->make(WooCommerceIntegrationInterface::class);
        });

        $this->app->singleton(\Pollora\Plugins\WooCommerce\View\WooCommerceTemplateResolver::class, function ($app) {
            return $app->make(TemplateResolverInterface::class);
        });
    }

    /**
     * Register application layer services.
     */
    private function registerApplicationServices(): void
    {
        $this->app->singleton(RegisterWooCommerceHooksUseCase::class, function ($app) {
            return new RegisterWooCommerceHooksUseCase(
                $app->make(Action::class),
                $app->make(Filter::class),
                $app->make(WooCommerceIntegrationInterface::class),
                $app->make(TemplateResolverInterface::class)
            );
        });
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