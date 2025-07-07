<?php

declare(strict_types=1);

namespace Pollora\Taxonomy\Infrastructure\Services;

use Illuminate\Support\Str;
use Pollora\Attributes\Taxonomy;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyServiceInterface;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Taxonomy Discovery Service
 *
 * Discovers classes decorated with Taxonomy attributes and registers them as custom taxonomies.
 * This discovery class scans for classes that have the #[Taxonomy] attribute and processes
 * all related sub-attributes to build a complete taxonomy configuration.
 *
 * Handles both class-level attributes (like Hierarchical, PublicTaxonomy, ShowUI) and method-level
 * attributes (like MetaBoxCb, UpdateCountCallback) by aggregating all configuration into a
 * complete WordPress taxonomy registration.
 */
final class TaxonomyDiscovery implements DiscoveryInterface
{
    use IsDiscovery;

    /**
     * Create a new Taxonomy discovery
     *
     * @param  TaxonomyServiceInterface  $taxonomyService  The taxonomy service for registration
     */
    public function __construct(
        private readonly TaxonomyServiceInterface $taxonomyService
    ) {}

    /**
     * {@inheritDoc}
     *
     * Discovers classes with Taxonomy attributes and collects them for registration.
     * Only processes classes that have the Taxonomy attribute and are instantiable.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        // Only process classes
        if (! $structure instanceof \Spatie\StructureDiscoverer\Data\DiscoveredClass) {
            return;
        }

        // Check if class has Taxonomy attribute
        $taxonomyAttribute = null;
        foreach ($structure->attributes as $attribute) {
            if ($attribute->class === Taxonomy::class) {
                $taxonomyAttribute = $attribute;
                break;
            }
        }

        if ($taxonomyAttribute === null) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        // Collect the class for registration
        $this->getItems()->add($location, [
            'class' => $structure->namespace.'\\'.$structure->name,
            'attribute' => $taxonomyAttribute,
            'structure' => $structure,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Applies discovered Taxonomy classes by processing all their attributes and
     * registering them as complete WordPress custom taxonomies. This includes
     * aggregating class-level and method-level attributes into a unified configuration.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'class' => $className,
                'attribute' => $taxonomyAttribute,
                'structure' => $structure
            ] = $discoveredItem;

            try {
                // Process the complete taxonomy configuration
                $this->processTaxonomy($className, $taxonomyAttribute);
            } catch (\Throwable $e) {
                // Log the error but continue with other taxonomies
                error_log("Failed to register Taxonomy from class {$className}: ".$e->getMessage());
            }
        }
    }

    /**
     * Process a complete taxonomy configuration from its class and attributes.
     *
     * This method handles the entire taxonomy registration process by:
     * 1. Processing the main Taxonomy attribute for basic configuration
     * 2. Aggregating all class-level sub-attributes
     * 3. Processing method-level attributes for callbacks
     * 4. Registering the final taxonomy through the service
     *
     * @param  string  $className  The fully qualified class name
     * @param  object  $taxonomyAttribute  The Spatie DiscoveredAttribute instance
     */
    private function processTaxonomy(string $className, object $taxonomyAttribute): void
    {
        try {
            // Use reflection to get the Taxonomy attribute instance
            $reflectionClass = new ReflectionClass($className);
            $taxonomyAttributes = $reflectionClass->getAttributes(Taxonomy::class);

            if (empty($taxonomyAttributes)) {
                return;
            }

            // Get the Taxonomy attribute instance using reflection
            /** @var Taxonomy $taxonomy */
            $taxonomy = $taxonomyAttributes[0]->newInstance();

            // Build base taxonomy configuration using the new configuration class
            $config = $this->buildBaseConfiguration($reflectionClass, $className, $taxonomy);

            // Process class-level attributes
            $config = $this->processClassLevelAttributes($reflectionClass, $className, $config);

            // Process method-level attributes
            $config = $this->processMethodLevelAttributes($reflectionClass, $className, $config);

            // Get additional arguments from the class instance if it has a withArgs method
            $this->processAdditionalArgs($className, $config);

            // Register the taxonomy through the service on the init hook
            add_action('init', function () use ($config) {
                $this->taxonomyService->register(
                    $config->getSlug(),
                    $config->getObjectType(),
                    $config->getName(),
                    $config->getPluralName(),
                    $config->getArgs()
                );
            });
        } catch (\ReflectionException $e) {
            error_log("Failed to process Taxonomy for class {$className}: ".$e->getMessage());
        }
    }

    /**
     * Build the base configuration from the main Taxonomy attribute.
     *
     * Extracts slug, singular name, plural name, and object type from the Taxonomy attribute,
     * applying auto-generation logic when values are not explicitly provided.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  string  $className  The class name for auto-generation
     * @param  Taxonomy  $taxonomy  The Taxonomy attribute instance
     * @return TaxonomyConfiguration The base configuration
     */
    private function buildBaseConfiguration(ReflectionClass $reflectionClass, string $className, Taxonomy $taxonomy): TaxonomyConfiguration
    {
        $slug = $this->generateSlug($className, $taxonomy->slug);
        $singular = $this->generateSingular($className, $taxonomy->singular);
        $plural = $this->generatePlural($className, $taxonomy->plural, $singular);
        $objectType = $taxonomy->objectType ?? ['post'];

        $initialArgs = [
            'labels' => $this->generateLabels($singular, $plural),
        ];

        return new TaxonomyConfiguration($slug, $singular, $plural, $objectType, $initialArgs);
    }

    /**
     * Process all class-level attributes to build taxonomy arguments.
     *
     * Scans the class for all known Taxonomy sub-attributes and processes them
     * to build the complete arguments array for WordPress taxonomy registration.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  string  $className  The class name to process
     * @param  TaxonomyConfiguration  $config  The current configuration
     * @return TaxonomyConfiguration The updated configuration
     */
    private function processClassLevelAttributes(ReflectionClass $reflectionClass, string $className, TaxonomyConfiguration $config): TaxonomyConfiguration
    {
        try {
            foreach ($reflectionClass->getAttributes() as $attribute) {
                if (str_contains($attribute->getName(), 'Pollora\\Attributes\\Taxonomy\\')) {
                    $this->processClassAttribute($reflectionClass, $attribute, $config);
                }
            }
        } catch (\ReflectionException $e) {
            error_log("Failed to process class-level attributes for {$className}: ".$e->getMessage());
        }

        return $config;
    }

    /**
     * Process method-level attributes to build callback configurations.
     *
     * Scans all public methods of the class for method-level attributes like
     * MetaBoxCb and UpdateCountCallback, building the appropriate callback configurations.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  string  $className  The class name to process
     * @param  TaxonomyConfiguration  $config  The current configuration
     * @return TaxonomyConfiguration The updated configuration
     */
    private function processMethodLevelAttributes(ReflectionClass $reflectionClass, string $className, TaxonomyConfiguration $config): TaxonomyConfiguration
    {
        try {
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes() as $attribute) {
                    // Process all method-level attributes that have a handle method
                    $this->processMethodAttribute($className, $method, $attribute, $config);
                }
            }
        } catch (\ReflectionException $e) {
            error_log("Failed to process method-level attributes for {$className}: ".$e->getMessage());
        }

        return $config;
    }

    /**
     * Process a single class-level attribute.
     *
     * Takes an attribute instance and applies its configuration to the taxonomy args.
     * Uses the attribute class name to determine the appropriate processing logic.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  \ReflectionAttribute  $attribute  The attribute to process
     * @param  TaxonomyConfiguration  $config  The current configuration
     */
    private function processClassAttribute(ReflectionClass $reflectionClass, \ReflectionAttribute $attribute, TaxonomyConfiguration $config): void
    {
        $attributeInstance = $attribute->newInstance();

        // Check if the attribute has a handle method and call it
        if (method_exists($attributeInstance, 'handle')) {
            $attributeInstance->handle(app(), $config, $reflectionClass, $attributeInstance);
        }
    }

    /**
     * Process a single method-level attribute.
     *
     * Handles method-level attributes like MetaBoxCb and UpdateCountCallback by
     * creating appropriate callback configurations.
     *
     * @param  string  $className  The class name
     * @param  ReflectionMethod  $method  The method with the attribute
     * @param  \ReflectionAttribute  $attribute  The attribute to process
     * @param  TaxonomyConfiguration  $config  The current configuration
     */
    private function processMethodAttribute(
        string $className,
        ReflectionMethod $method,
        \ReflectionAttribute $attribute,
        TaxonomyConfiguration $config
    ): void {
        $attributeInstance = $attribute->newInstance();

        // Check if the attribute has a handle method and call it
        if (method_exists($attributeInstance, 'handle')) {
            $attributeInstance->handle(app(), $config, $method, $attributeInstance);
        }
    }

    /**
     * Process additional arguments from the class instance.
     *
     * If the class has a withArgs method, it will be called to get additional
     * arguments that should be merged into the taxonomy configuration.
     *
     * @param  string  $className  The class name to process
     * @param  TaxonomyConfiguration  $config  The current configuration
     */
    private function processAdditionalArgs(string $className, TaxonomyConfiguration $config): void
    {
        try {
            // Try to instantiate the class
            $reflectionClass = new ReflectionClass($className);

            if ($reflectionClass->isInstantiable()) {
                $instance = $reflectionClass->newInstance();

                // Check if the instance has a withArgs method
                if (method_exists($instance, 'withArgs')) {
                    $additionalArgs = $instance->withArgs();

                    if (is_array($additionalArgs) && ! empty($additionalArgs)) {
                        $config->mergeArgs($additionalArgs);
                    }
                }
            }
        } catch (\ReflectionException|\Throwable $e) {
            // Log the error but continue - additional args are optional
            error_log("Failed to process additional args for {$className}: ".$e->getMessage());
        }
    }

    /**
     * Generate a taxonomy slug from class name and attribute value.
     *
     * @param  string  $className  The class name
     * @param  string|null  $attributeSlug  The slug from the attribute
     * @return string The generated slug
     */
    private function generateSlug(string $className, ?string $attributeSlug): string
    {
        if ($attributeSlug !== null) {
            return $attributeSlug;
        }

        return Str::kebab(class_basename($className));
    }

    /**
     * Generate a singular name from class name and attribute value.
     *
     * @param  string  $className  The class name
     * @param  string|null  $attributeSingular  The singular name from the attribute
     * @return string The generated singular name
     */
    private function generateSingular(string $className, ?string $attributeSingular): string
    {
        if ($attributeSingular !== null) {
            return $attributeSingular;
        }

        $baseName = class_basename($className);
        $snakeCase = Str::snake($baseName);
        $humanized = ucfirst(str_replace('_', ' ', $snakeCase));

        return Str::singular($humanized);
    }

    /**
     * Generate a plural name from singular name and attribute value.
     *
     * @param  string  $className  The class name
     * @param  string|null  $attributePlural  The plural name from the attribute
     * @param  string  $singular  The singular name
     * @return string The generated plural name
     */
    private function generatePlural(string $className, ?string $attributePlural, string $singular): string
    {
        if ($attributePlural !== null) {
            return $attributePlural;
        }

        return Str::plural($singular);
    }

    /**
     * Generate WordPress labels array from singular and plural names.
     *
     * @param  string  $singular  The singular name
     * @param  string  $plural  The plural name
     * @return array<string, string> The labels array
     */
    private function generateLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'menu_name' => $plural,
            'all_items' => "All {$plural}",
            'edit_item' => "Edit {$singular}",
            'view_item' => "View {$singular}",
            'update_item' => "Update {$singular}",
            'add_new_item' => "Add New {$singular}",
            'new_item_name' => "New {$singular} Name",
            'search_items' => "Search {$plural}",
            'popular_items' => "Popular {$plural}",
            'separate_items_with_commas' => "Separate {$plural} with commas",
            'add_or_remove_items' => "Add or remove {$plural}",
            'choose_from_most_used' => "Choose from the most used {$plural}",
            'not_found' => "No {$plural} found",
            'parent_item' => "Parent {$singular}",
            'parent_item_colon' => "Parent {$singular}:",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'taxonomies';
    }
}
