<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRegistryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeRepositoryInterface;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\PostType\Infrastructure\Adapters\WordPressPostTypeRegistry;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;
use Pollora\PostType\Infrastructure\Repositories\PostTypeRepository;
use Pollora\PostType\Infrastructure\Services\PostTypeDiscovery;
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
        $this->app->singleton(PostTypeRepositoryInterface::class, fn ($app): \Pollora\PostType\Infrastructure\Repositories\PostTypeRepository => new PostTypeRepository(
            $app->make(PostTypeRegistryInterface::class)
        ));

        // Register the PostTypeService with interface binding
        $this->app->singleton(PostTypeServiceInterface::class, fn ($app): \Pollora\PostType\Application\Services\PostTypeService => new PostTypeService(
            $app->make(PostTypeFactoryInterface::class),
            $app->make(PostTypeRegistryInterface::class)
        ));

        // Also bind concrete class for backward compatibility
        $this->app->singleton(PostTypeService::class, fn ($app) => $app->make(PostTypeServiceInterface::class));

        // Register PostType Discovery
        $this->app->singleton(PostTypeDiscovery::class, fn ($app): \Pollora\PostType\Infrastructure\Services\PostTypeDiscovery => new PostTypeDiscovery(
            $app->make(PostTypeServiceInterface::class)
        ));

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

        // Register PostType discovery with the discovery engine
        $this->registerPostTypeDiscovery();
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

    /**
     * Register PostType discovery with the discovery engine.
     */
    private function registerPostTypeDiscovery(): void
    {
        if ($this->app->bound(DiscoveryEngineInterface::class)) {
            /** @var DiscoveryEngineInterface $engine */
            $engine = $this->app->make(DiscoveryEngineInterface::class);
            $postTypeDiscovery = $this->app->make(PostTypeDiscovery::class);

            $engine->addDiscovery('post_types', $postTypeDiscovery);
        }
    }
}
