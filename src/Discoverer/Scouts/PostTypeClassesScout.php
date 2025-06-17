<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Illuminate\Support\Collection;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\HandlerScoutInterface;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\PostType\Application\Services\PostTypeService;
use Pollora\PostType\Domain\Models\AbstractPostType;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom post type classes.
 *
 * This scout finds all classes that extend AbstractPostType across
 * the application, modules, themes, and plugins for automatic
 * WordPress post type registration.
 */
final class PostTypeClassesScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractPostType::class);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        $discoveredClasses = $this->get();

        if (empty($discoveredClasses)) {
            return;
        }

        try {
            $processor = new AttributeProcessor($this->container);
            $postTypeService = $this->container->make(PostTypeService::class);

            foreach ($discoveredClasses as $postTypeClass) {
                $this->registerPostType($postTypeClass, $processor, $postTypeService);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to handle post types: '.$e->getMessage());
            }
        }
    }

    /**
     * Register a single post type with WordPress.
     *
     * @param  string  $postTypeClass  The fully qualified class name of the post type
     * @param  AttributeProcessor  $processor  The attribute processor
     * @param  PostTypeService  $postTypeService  The post type service
     */
    private function registerPostType(
        string $postTypeClass,
        AttributeProcessor $processor,
        PostTypeService $postTypeService
    ): void {
        $postTypeInstance = $this->container->make($postTypeClass);

        if (! $postTypeInstance instanceof AbstractPostType) {
            return;
        }

        // Process attributes
        $processor->process($postTypeInstance);

        // Get post type properties
        $slug = $postTypeInstance->getSlug();
        $singular = $postTypeInstance->getName();
        $plural = $postTypeInstance->getPluralName();
        $args = $postTypeInstance->getArgs();

        $postTypeService->register($slug, $singular, $plural, $args);
    }
}
