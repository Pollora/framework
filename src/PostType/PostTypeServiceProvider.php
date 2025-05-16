<?php

declare(strict_types=1);

/**
 * Class PostTypeServiceProvider
 */

namespace Pollora\PostType;

use Illuminate\Support\ServiceProvider;
use Pollora\Entity\PostType;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\Console\Application\Services\ConsoleDetectionService;

/**
 * Service provider for registering custom post types.
 *
 * This provider handles the registration of custom post types in WordPress,
 * integrating them with Laravel's service container and allowing for
 * configuration-based post type registration.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    /**
     * @var ConsoleDetectionService
     */
    protected ConsoleDetectionService $consoleDetectionService;

    public function __construct($app, ConsoleDetectionService $consoleDetectionService = null)
    {
        parent::__construct($app);
        $this->consoleDetectionService = $consoleDetectionService ?? app(ConsoleDetectionService::class);
    }

    /**
     * Register post type services.
     *
     * Binds the PostTypeFactory and PostTypeService to the service container
     * following the hexagonal architecture principles.
     */
    public function register(): void
    {
        // Bind the interface to the concrete implementation
        $this->app->singleton(PostTypeFactoryInterface::class, PostTypeFactory::class);

        // Register the PostTypeService
        $this->app->singleton(PostTypeService::class, function ($app) {
            return new PostTypeService(
                $app->make(PostTypeFactoryInterface::class)
            );
        });

        // Legacy bindings for backward compatibility
        $this->app->alias(PostTypeFactoryInterface::class, 'wp.posttype');

        $this->registerPostTypes();

        // Register the attribute-based post type service provider
        $this->app->register(PostTypeAttributeServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->consoleDetectionService->isConsole()) {
            $this->publishes([
                __DIR__.'/config/posttype.php' => config_path('posttype.php'),
            ], 'pollora-posttype-config');
        }
    }

    /**
     * Register all configured custom post types.
     *
     * Reads post type configurations from the config file and registers
     * each post type using the PostTypeService, following hexagonal architecture
     * principles by using dependency injection instead of facades.
     *
     * @example Configuration format:
     * [
     *     'book' => [
     *         'names' => [
     *             'singular' => 'Book',
     *             'plural' => 'Books',
     *             'slug' => 'books'
     *         ],
     *         // Additional WordPress post type arguments...
     *     ]
     * ]
     */
    public function registerPostTypes(): void
    {
        // Get the post types from the config
        $postTypes = $this->app['config']->get('post-types', []);

        // Resolve the service from the container
        $postTypeService = $this->app->make(PostTypeService::class);

        // Iterate over each post type
        collect($postTypes)->each(function (array $args, string $key) use ($postTypeService): void {
            // Get the singular and plural names
            $singular = $args['names']['singular'] ?? null;
            $plural = $args['names']['plural'] ?? null;

            // Create the post type instance using the service
            $postTypeService->register($key, $singular, $plural);
        });
    }
}
