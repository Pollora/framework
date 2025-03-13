<?php

declare(strict_types=1);

namespace Pollora\Taxonomy;

use Illuminate\Support\ServiceProvider;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Taxonomy\Commands\TaxonomyMakeCommand;
use Pollora\Taxonomy\Contracts\Taxonomy;
use Spatie\StructureDiscoverer\Discover;

/**
 * Service provider for attribute-based taxonomy registration.
 *
 * This provider discovers and registers all classes implementing the Taxonomy interface
 * and processes their PHP attributes to configure WordPress custom taxonomies.
 */
class TaxonomyAttributeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the make:taxonomy command
        if ($this->app->runningInConsole()) {
            $this->commands([
                TaxonomyMakeCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     *
     * Discovers and registers all taxonomies defined using PHP attributes.
     */
    public function boot(): void
    {
        $this->registerTaxonomies();
    }

    /**
     * Register all taxonomies defined using PHP attributes.
     *
     * Discovers all classes implementing the Taxonomy interface, processes their
     * attributes, and registers them with WordPress.
     */
    protected function registerTaxonomies(): void
    {
        // Check if the directory exists before attempting to discover classes
        $directory = app_path('Cms/Taxonomies');
        if (! is_dir($directory)) {
            return; // Return early as there are no classes to discover yet
        }

        // Discover all classes implementing the Taxonomy interface
        $taxonomyClasses = Discover::in($directory)
            ->extending(AbstractTaxonomy::class)
            ->classes()
            ->get();

        // Register each taxonomy with WordPress
        if (! empty($taxonomyClasses)) {
            foreach ($taxonomyClasses as $taxonomyClass) {
                $this->registerTaxonomy($taxonomyClass);
            }
        }
    }

    /**
     * Register a single taxonomy with WordPress.
     *
     * Creates an instance of the taxonomy class, processes its attributes,
     * and registers it with WordPress using register_taxonomy().
     *
     * @param  string  $taxonomyClass  The fully qualified class name of the taxonomy
     */
    protected function registerTaxonomy(string $taxonomyClass): void
    {
        $taxonomy = $this->app->make($taxonomyClass);

        // Process attributes
        AttributeProcessor::process($taxonomy);

        // Register the taxonomy with WordPress
        if (function_exists('register_extended_taxonomy')) {
            register_extended_taxonomy(
                $taxonomy->getSlug(),
                $taxonomy->getObjectType(),
                $taxonomy->getArgs()
            );
        }
    }
}
