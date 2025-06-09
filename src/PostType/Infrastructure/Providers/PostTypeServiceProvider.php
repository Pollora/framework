<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRepositoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Repositories\PostTypeRepository;
use Pollora\PostType\UI\Console\PostTypeMakeCommand;

/**
 * Service provider for post type functionality.
 *
 * This provider registers all the necessary services, factories, and repositories
 * following hexagonal architecture principles and dependency injection patterns.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    /**
     * Register the post type services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->singleton(PostTypeFactoryInterface::class, PostTypeFactory::class);
        $this->app->singleton(PostTypeRegistryInterface::class, WordPressPostTypeRegistry::class);

        // Register the repository
        $this->app->singleton(PostTypeRepositoryInterface::class, function ($app) {
            return new PostTypeRepository(
                $app->make(PostTypeRegistryInterface::class)
            );
        });

        // Register the PostTypeService with interface binding
        $this->app->singleton(PostTypeServiceInterface::class, function ($app) {
            return new PostTypeService(
                $app->make(PostTypeFactoryInterface::class),
                $app->make(PostTypeRegistryInterface::class)
            );
        });

        // Also bind concrete class for backward compatibility
        $this->app->singleton(PostTypeService::class, function ($app) {
            return $app->make(PostTypeServiceInterface::class);
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PostTypeMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/post-types.php' => config_path('post-types.php'),
            ], 'pollora-posttype-config');
        }

        // Register post types from configuration
        $this->registerConfiguredPostTypes();
    }

    /**
     * Register post types defined in the configuration.
     */
    private function registerConfiguredPostTypes(): void
    {
        // Get the post types from the config
        $postTypes = $this->app['config']->get('post-types', []);

        if (empty($postTypes)) {
            return;
        }

        // Resolve the service from the container using the interface
        $postTypeService = $this->app->make(PostTypeServiceInterface::class);

        // Register each post type
        foreach ($postTypes as $slug => $config) {
            if (! is_array($config)) {
                continue;
            }

            $singular = $config['names']['singular'] ?? null;
            $plural = $config['names']['plural'] ?? null;
            $args = $config['args'] ?? [];

            $postTypeService->register($slug, $singular, $plural, $args);
        }
    }
}
