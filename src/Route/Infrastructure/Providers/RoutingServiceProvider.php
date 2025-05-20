<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Providers;

use Illuminate\Container\Container;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Application\Services\AuthorizerService;
use Pollora\Route\Application\Services\BodyClassService;
use Pollora\Route\Application\Services\HeaderManagerService;
use Pollora\Route\Application\Services\RouteBindingService;
use Pollora\Route\Application\Services\ShutdownHandlerService;
use Pollora\Route\Domain\Contracts\AuthorizerInterface;
use Pollora\Route\Domain\Contracts\BindingServiceInterface;
use Pollora\Route\Domain\Contracts\BodyClassServiceInterface;
use Pollora\Route\Domain\Contracts\ConditionValidatorInterface;
use Pollora\Route\Domain\Contracts\HeaderManagerInterface;
use Pollora\Route\Domain\Contracts\RouteRegistrarInterface;
use Pollora\Route\Domain\Contracts\RouterInterface;
use Pollora\Route\Domain\Contracts\ShutdownHandlerInterface;
use Pollora\Route\Domain\Services\ConditionValidator as DomainConditionValidator;
use Pollora\Route\Infrastructure\Adapters\LaravelRoute;
use Pollora\Route\Infrastructure\Adapters\LaravelRouteCollection;
use Pollora\Route\Infrastructure\Adapters\LaravelRouter;
use Pollora\Route\Infrastructure\Adapters\LaravelRouteRegistrar;
use Pollora\Route\Infrastructure\Adapters\Router;

/**
 * Service provider for WordPress routing functionality.
 *
 * This provider registers the router and related services in
 * accordance with Hexagonal Architecture principles.
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Laravel router dependencies
        $this->app->singleton(CallableDispatcherContract::class, CallableDispatcher::class);
        
        // Bind the domain services
        $this->app->singleton(ConditionValidatorInterface::class, DomainConditionValidator::class);

        // Bind application services
        $this->app->singleton(BodyClassServiceInterface::class, BodyClassService::class);
        $this->app->singleton(BindingServiceInterface::class, RouteBindingService::class);
        $this->app->singleton(HeaderManagerInterface::class, HeaderManagerService::class);
        $this->app->singleton(AuthorizerInterface::class, AuthorizerService::class);
        $this->app->singleton(ShutdownHandlerInterface::class, ShutdownHandlerService::class);

        // Bind adapters
        $this->app->singleton(LaravelRoute::class);
        $this->app->singleton(LaravelRouteCollection::class);
    }
}
