<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Laravel;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Pollora\Route\Application\Services\BuildTemplateHierarchyService;
use Pollora\Route\Application\Services\HandleSpecialRequestService;
use Pollora\Route\Application\Services\RegisterWordPressRouteService;
use Pollora\Route\Application\Services\ResolveRouteService;
use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Contracts\RouteMatcherInterface;
use Pollora\Route\Domain\Contracts\RouteRegistryInterface;
use Pollora\Route\Domain\Contracts\SpecialRequestHandlerInterface;
use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Services\ConditionValidator;
use Pollora\Route\Domain\Services\RoutePriorityResolver;
use Pollora\Route\Domain\Services\SpecialRequestDetector;
use Pollora\Route\Domain\Services\TemplatePriorityComparator;
use Pollora\Route\Domain\Services\WordPressContextBuilder;
use Pollora\Route\Infrastructure\Repositories\InMemoryRouteRegistry;
use Pollora\Route\Infrastructure\Services\LaravelRouteMatcher;
use Pollora\Route\Infrastructure\WordPress\ConditionalTagsResolver;
use Pollora\Route\Infrastructure\WordPress\WordPressSpecialRequestHandler;
use Pollora\Route\Infrastructure\WordPress\WordPressTemplateResolver;
use Pollora\Route\UI\Http\Controllers\FrontendController;

/**
 * Route service provider for WordPress hybrid routing
 *
 * Registers the WordPress routing system with Laravel and sets up
 * all necessary dependencies and route macros.
 */
final class RouteServiceProvider extends ServiceProvider
{
    private bool $macrosRegistered = false;

    /**
     * Register services
     */
    public function register(): void
    {
        // Register core contracts
        $this->registerContracts();

        // Register domain services
        $this->registerDomainServices();

        // Register application services
        $this->registerApplicationServices();

        // Extend Laravel router
        $this->extendLaravelRouter();
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->app->singleton(ConditionResolverInterface::class, fn ($app) =>
            new ConditionalTagsResolver($app['config']->get('wordpress', []))
        );

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../../../config/wordpress.php' => config_path('wordpress.php'),
        ], 'pollora-config');

        // Merge configuration with defaults
        $this->mergeConfigFrom(__DIR__ . '/../../../../config/wordpress.php', 'wordpress');

        // Register WordPress route macros again in boot for safety
        $this->registerWordPressRouteMacros(); // Temporarily disabled

        // Configure special request detection
        $this->configureSpecialRequestDetection();

        // Boot fallback route after application is fully loaded
        $this->app->booted(function (): void {
            $this->bootFallbackRoute();
        });
    }

    /**
     * Register core contracts with their implementations
     */
    private function registerContracts(): void
    {
        // Route registry
        $this->app->singleton(RouteRegistryInterface::class, InMemoryRouteRegistry::class);

        // Route matcher
        $this->app->singleton(RouteMatcherInterface::class, LaravelRouteMatcher::class);

        // Condition resolver
        $this->app->singleton(ConditionResolverInterface::class, function ($app) {
            // Get WordPress config with fallback to default configuration
            $config = $this->getWordPressConfig($app);
            return new ConditionalTagsResolver($config);
        });

        // Template resolver
        $this->app->singleton(TemplateResolverInterface::class, function ($app) {
            return new WordPressTemplateResolver(
                $app->make(WordPressContextBuilder::class),
                $app->make(ConditionResolverInterface::class),
                []
            );
        });

        // Special request handler
        $this->app->singleton(SpecialRequestHandlerInterface::class, function ($app) {
            return new WordPressSpecialRequestHandler([]);
        });
    }

    /**
     * Register domain services
     */
    private function registerDomainServices(): void
    {
        $this->app->singleton(RoutePriorityResolver::class, function ($app) {
            $resolver = new RoutePriorityResolver();
            $resolver->setTemplatePriorityComparator($app->make(TemplatePriorityComparator::class));
            return $resolver;
        });
        $this->app->singleton(SpecialRequestDetector::class);
        $this->app->singleton(WordPressContextBuilder::class);

        $this->app->singleton(ConditionValidator::class, function ($app) {
            return new ConditionValidator(
                $app->make(ConditionResolverInterface::class)
            );
        });

        $this->app->singleton(TemplatePriorityComparator::class, function ($app) {
            // Get template priority config with fallback
            $config = $this->getTemplatePriorityConfig($app);
            return new TemplatePriorityComparator(
                $app->make(TemplateResolverInterface::class),
                $config
            );
        });
    }

    /**
     * Register application services
     */
    private function registerApplicationServices(): void
    {
        // Register WordPress route service
        $this->app->singleton(RegisterWordPressRouteService::class, function ($app) {
            return new RegisterWordPressRouteService(
                $app->make(RouteRegistryInterface::class),
                $app->make(ConditionResolverInterface::class),
                $app->make(ConditionValidator::class),
                []
            );
        });

        // Route resolution service
        $this->app->singleton(ResolveRouteService::class, function ($app) {
            return new ResolveRouteService(
                $app->make(RouteMatcherInterface::class),
                $app->make(TemplateResolverInterface::class),
                $app->make(SpecialRequestHandlerInterface::class),
                $app->make(SpecialRequestDetector::class),
                $app->make(RoutePriorityResolver::class),
                $app->make(WordPressContextBuilder::class)
            );
        });

        // Template hierarchy service
        $this->app->singleton(BuildTemplateHierarchyService::class, function ($app) {
            return new BuildTemplateHierarchyService(
                $app->make(TemplateResolverInterface::class),
                $app->make(WordPressContextBuilder::class),
                []
            );
        });

        // Special request service
        $this->app->singleton(HandleSpecialRequestService::class, function ($app) {
            return new HandleSpecialRequestService(
                $app->make(SpecialRequestHandlerInterface::class),
                $app->make(SpecialRequestDetector::class),
                []
            );
        });
    }

    /**
     * Extend Laravel router with WordPress functionality
     */
    private function extendLaravelRouter(): void
    {
        $this->app->extend('router', function (Router $router, Container $app): ExtendedRouter {
            $extendedRouter = new ExtendedRouter($app->make('events'), $app);

            // Copy existing routes
            foreach ($router->getRoutes() as $route) {
                $extendedRouter->getRoutes()->add($route);
            }

            // Configure WordPress integration
            $extendedRouter->setSpecialRequestDetector($app->make(SpecialRequestDetector::class));
            $extendedRouter->setSpecialRequestHandler($app->make(SpecialRequestHandlerInterface::class));
            $extendedRouter->setRouteRegistry($app->make(RouteRegistryInterface::class));

            // Inject services for template priority checking
            $extendedRouter->setTemplatePriorityComparator($app->make(TemplatePriorityComparator::class));
            $extendedRouter->setTemplateHierarchyService($app->make(BuildTemplateHierarchyService::class));
            $extendedRouter->setConditionResolver($app->make(ConditionResolverInterface::class));

            return $extendedRouter;
        });
    }

    /**
     * Register WordPress route macros
     */
    private function registerWordPressRouteMacros(): void
    {
        // Skip if already registered
        if ($this->macrosRegistered) {
            return;
        }

        // WordPress route macro for all HTTP methods
        Route::macro('wp', function (string $condition, ...$args) {
            if (empty($args)) {
                throw new \InvalidArgumentException('WordPress route requires at least a condition and a callback.');
            }

            $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

            return Route::wpMatch($verbs, $condition, ...$args);
        });

        // WordPress route macro for specific HTTP methods
        Route::macro('wpMatch', function (array|string $methods, string $condition, ...$args) {
            if (empty($args)) {
                throw new \InvalidArgumentException('WordPress route requires at least a condition and a callback.');
            }

            // Ensure methods is an array
            $methods = is_string($methods) ? [$methods] : $methods;

            // Resolve the condition alias using the condition resolver
            $conditionResolver = app(ConditionResolverInterface::class);
            $resolvedCondition = $conditionResolver->resolveAlias($condition);

            // Validate that the condition exists and parameters are correct
            $conditionParams = count($args) > 1 ? array_slice($args, 0, -1) : [];

            if (!$conditionResolver->hasCondition($resolvedCondition)) {
                throw new \InvalidArgumentException(
                    "WordPress condition '{$condition}' (resolved to '{$resolvedCondition}') is not available."
                );
            }

            if (!$conditionResolver->validateParameters($resolvedCondition, $conditionParams)) {
                throw new \InvalidArgumentException(
                    "Invalid parameters for WordPress condition '{$resolvedCondition}'."
                );
            }

            // Use the extended router's WordPress route functionality
            $router = app('router');

            if ($router instanceof ExtendedRouter) {
                // Use the resolved condition instead of the original alias
                $route = $router->addWordPressRoute($methods, $resolvedCondition, ...$args);

                // Apply WordPress middleware automatically
                $route->applyWordPressMiddleware();

                return $route;
            }

            // Fallback for non-extended router
            throw new \RuntimeException('Extended router not available for WordPress routes');
        });

        // WordPress GET route macro
        Route::macro('wpGet', function (string $condition, ...$args) {
            return Route::wpMatch(['GET', 'HEAD'], $condition, ...$args);
        });

        // WordPress POST route macro
        Route::macro('wpPost', function (string $condition, ...$args) {
            return Route::wpMatch(['POST'], $condition, ...$args);
        });

        // WordPress PUT route macro
        Route::macro('wpPut', function (string $condition, ...$args) {
            return Route::wpMatch(['PUT'], $condition, ...$args);
        });

        // WordPress PATCH route macro
        Route::macro('wpPatch', function (string $condition, ...$args) {
            return Route::wpMatch(['PATCH'], $condition, ...$args);
        });

        // WordPress DELETE route macro
        Route::macro('wpDelete', function (string $condition, ...$args) {
            return Route::wpMatch(['DELETE'], $condition, ...$args);
        });

        // WordPress OPTIONS route macro
        Route::macro('wpOptions', function (string $condition, ...$args) {
            return Route::wpMatch(['OPTIONS'], $condition, ...$args);
        });

        $this->macrosRegistered = true;
    }

    /**
     * Configure special request detection for the extended router
     */
    private function configureSpecialRequestDetection(): void
    {
        $router = $this->app->make('router');

        if ($router instanceof ExtendedRouter) {
            $router->setSpecialRequestDetector($this->app->make(SpecialRequestDetector::class));
            $router->setSpecialRequestHandler($this->app->make(SpecialRequestHandlerInterface::class));

            // Ensure template priority checking services are injected
            $router->setTemplatePriorityComparator($this->app->make(TemplatePriorityComparator::class));
            $router->setTemplateHierarchyService($this->app->make(BuildTemplateHierarchyService::class));
            $router->setConditionResolver($this->app->make(ConditionResolverInterface::class));
        }
    }

    /**
     * Boot the fallback route for WordPress template hierarchy
     */
    private function bootFallbackRoute(): void
    {
        $router = $this->app->make('router');

        $fallbackRoute = $router->any('{any}', [FrontendController::class, 'handle'])
             ->where('any', '.*')
             ->fallback();
        $fallbackRoute->name('wordpress.fallback');
    }

    /**
     * Get WordPress configuration with fallback to default values
     */
    private function getWordPressConfig($app): array
    {
        try {
            // Try to get configuration from Laravel config
            if ($app->bound('config')) {
                $config = $app['config']->get('wordpress', []);
                if (!empty($config)) {
                    return $config;
                }
            }
        } catch (\Throwable) {
            // Ignore errors and fallback to default config
        }

        // Fallback to loading the default configuration file directly
        return $this->getDefaultWordPressConfig();
    }

    /**
     * Get template priority configuration with fallback
     */
    private function getTemplatePriorityConfig($app): array
    {
        try {
            // Try to get configuration from Laravel config
            if ($app->bound('config')) {
                $config = $app['config']->get('wordpress.routing.priority', []);
                if (!empty($config)) {
                    return $config;
                }
            }
        } catch (\Throwable) {
            // Ignore errors and fallback to default config
        }

        // Fallback to default template priority configuration
        return $this->getDefaultTemplatePriorityConfig();
    }

    /**
     * Load default WordPress configuration from file
     */
    private function getDefaultWordPressConfig(): array
    {
        $configPath = __DIR__ . '/../../../config/wordpress.php';

        if (file_exists($configPath)) {
            try {
                return require $configPath;
            } catch (\Throwable) {
                // If file can't be loaded, return default configuration
            }
        }

        // Ultimate fallback configuration
        return [
            'conditions' => [
                'is_embed' => 'embed',
                'is_404' => '404',
                'is_search' => 'search',
                'is_front_page' => ['front', '/'],
                'is_home' => ['home', 'blog'],
                'is_single' => 'single',
                'is_page' => 'page',
                'is_singular' => 'singular',
                'is_category' => ['category', 'cat'],
                'is_tag' => 'tag',
                'is_author' => 'author',
                'is_archive' => 'archive',
            ],
            'plugin_conditions' => [
                'woocommerce' => [
                    'is_shop' => 'shop',
                    'is_product' => 'product',
                    'is_cart' => 'cart',
                    'is_checkout' => 'checkout',
                    'is_product_category' => 'product_category',
                    'is_product_tag' => 'product_tag',
                ],
            ],
        ];
    }

    /**
     * Get default template priority configuration
     */
    private function getDefaultTemplatePriorityConfig(): array
    {
        return [
            'template_existence_bonus' => 200,
            'route_parameter_weight' => 25,
            'template_depth_weight' => 50,
            'template_specificity_multiplier' => 2,
            'route_condition_weight' => 0.5,
            'same_specificity_prefers_template' => true,
            'laravel_route_override_threshold' => 1500,
            'debug_comparison' => false,
        ];
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            RouteRegistryInterface::class,
            RouteMatcherInterface::class,
            ConditionResolverInterface::class,
            TemplateResolverInterface::class,
            SpecialRequestHandlerInterface::class,
            RoutePriorityResolver::class,
            SpecialRequestDetector::class,
            ConditionValidator::class,
            WordPressContextBuilder::class,
            RegisterWordPressRouteService::class,
            ResolveRouteService::class,
            BuildTemplateHierarchyService::class,
            HandleSpecialRequestService::class,
        ];
    }
}
