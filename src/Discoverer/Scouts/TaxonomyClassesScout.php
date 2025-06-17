<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Illuminate\Support\Collection;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Domain\Contracts\HandlerScoutInterface;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Taxonomy\Application\Services\TaxonomyService;
use Pollora\Taxonomy\Domain\Models\AbstractTaxonomy;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom taxonomy classes.
 *
 * This scout finds all classes that extend AbstractTaxonomy across
 * the application, modules, themes, and plugins for automatic
 * WordPress taxonomy registration.
 */
final class TaxonomyClassesScout extends AbstractPolloraScout implements HandlerScoutInterface
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractTaxonomy::class);
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
            $taxonomyService = $this->container->make(TaxonomyService::class);

            foreach ($discoveredClasses as $taxonomyClass) {
                $this->registerTaxonomy($taxonomyClass, $processor, $taxonomyService);
            }
        } catch (\Throwable $e) {
            // Log error but don't break the application
            if (function_exists('error_log')) {
                error_log('Failed to handle taxonomies: '.$e->getMessage());
            }
        }
    }

    /**
     * Register a single taxonomy with WordPress.
     *
     * @param  string  $taxonomyClass  The fully qualified class name of the taxonomy
     * @param  AttributeProcessor  $processor  The attribute processor
     * @param  TaxonomyService  $taxonomyService  The taxonomy service
     */
    private function registerTaxonomy(
        string $taxonomyClass,
        AttributeProcessor $processor,
        TaxonomyService $taxonomyService
    ): void {
        $taxonomyInstance = $this->container->make($taxonomyClass);

        if (! $taxonomyInstance instanceof AbstractTaxonomy) {
            return;
        }

        // Process attributes
        $processor->process($taxonomyInstance);

        // Get taxonomy properties
        $slug = $taxonomyInstance->getSlug();
        $objectType = $taxonomyInstance->getObjectType();
        $singular = $taxonomyInstance->getName();
        $plural = $taxonomyInstance->getPluralName();
        $args = $taxonomyInstance->getArgs();

        $taxonomyService->register($slug, $objectType, $singular, $plural, $args);
    }
}
