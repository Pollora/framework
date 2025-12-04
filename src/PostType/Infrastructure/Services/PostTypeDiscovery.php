<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Services;

use Illuminate\Support\Str;
use Pollora\Attributes\PostType;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * PostType Discovery Service
 *
 * Discovers classes decorated with PostType attributes and registers them as custom post types.
 * This discovery class scans for classes that have the #[PostType] attribute and processes
 * all related sub-attributes to build a complete post type configuration.
 *
 * Handles both class-level attributes (like HasArchive, Supports, MenuIcon) and method-level
 * attributes (like AdminCol, RegisterMetaBoxCb) by aggregating all configuration into a
 * complete WordPress post type registration.
 */
final class PostTypeDiscovery implements DiscoveryInterface
{
    use HasInstancePool, IsDiscovery;

    /**
     * Create a new PostType discovery service.
     *
     * @param  PostTypeServiceInterface  $postTypeService  The post type service for registration
     */
    public function __construct(
        private readonly PostTypeServiceInterface $postTypeService
    ) {}

    /**
     * {@inheritDoc}
     *
     * Discovers classes with PostType attributes and collects them for registration.
     * Only processes classes that have the PostType attribute and are instantiable.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        // Only process classes
        if (! $structure instanceof \Spatie\StructureDiscoverer\Data\DiscoveredClass) {
            return;
        }

        // Check if class has PostType attribute
        $postTypeAttribute = null;

        foreach ($structure->attributes as $attribute) {
            if ($attribute->class === PostType::class) {
                $postTypeAttribute = $attribute;
                break;
            }
        }

        if ($postTypeAttribute === null) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        // Collect the class for registration
        $this->getItems()->add($location, [
            'class' => $structure->namespace.'\\'.$structure->name,
            'attribute' => $postTypeAttribute,
            'structure' => $structure,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Applies discovered PostType classes by processing all their attributes and
     * registering them as complete WordPress custom post types. This includes
     * aggregating class-level and method-level attributes into a unified configuration.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            [
                'class' => $className,
                'attribute' => $postTypeAttribute,
                'structure' => $structure
            ] = $discoveredItem;

            try {
                // Process the complete post type configuration
                $this->processPostType($className);
            } catch (\Throwable $e) {
                // Log the error but continue with other post types
                error_log("Failed to register PostType from class {$className}: ".$e->getMessage());
            }
        }
    }

    /**
     * Process a complete post type configuration from its class and attributes.
     *
     * This method handles the entire post type registration process by:
     * 1. Processing the main PostType attribute for basic configuration
     * 2. Aggregating all class-level sub-attributes
     * 3. Processing method-level attributes for callbacks
     * 4. Registering the final post type through the service
     *
     * @param  string  $className  The fully qualified class name
     */
    private function processPostType(string $className): void
    {
        try {
            // Use reflection to get the PostType attribute instance
            $reflectionClass = new ReflectionClass($className);
            $postTypeAttributes = $reflectionClass->getAttributes(PostType::class);

            if ($postTypeAttributes === []) {
                return;
            }

            // Get the PostType attribute instance using reflection
            /** @var PostType $postType */
            $postType = $postTypeAttributes[0]->newInstance();

            // Build base post type configuration using the new configuration class
            $config = $this->buildBaseConfiguration($className, $postType);

            // Process class-level attributes
            $config = $this->processClassLevelAttributes($reflectionClass, $className, $config);

            // Process method-level attributes
            $config = $this->processMethodLevelAttributes($reflectionClass, $className, $config);

            // Get additional arguments from the class instance if it has a withArgs method
            $this->processAdditionalArgs($className, $config);

            // Register the post type
            $this->postTypeService->register(
                $config->getSlug(),
                $config->getName(),
                $config->getPluralName(),
                $config->getArgs(),
                $config->getPriority()
            );

        } catch (\ReflectionException $e) {
            error_log("Failed to process PostType for class {$className}: ".$e->getMessage());
        }
    }

    /**
     * Build the base configuration from the main PostType attribute.
     *
     * Extracts slug, singular name, and plural name from the PostType attribute,
     * applying auto-generation logic when values are not explicitly provided.
     *
     * @param  string  $className  The class name for auto-generation
     * @param  PostType  $postType  The PostType attribute instance
     * @return PostTypeConfiguration The base configuration
     */
    private function buildBaseConfiguration(string $className, PostType $postType): PostTypeConfiguration
    {
        $slug = $this->generateSlug($className, $postType->slug);
        $singular = $this->generateSingular($className, $postType->singular);
        $plural = $this->generatePlural($postType->plural, $singular);

        $initialArgs = [
            'labels' => $this->generateLabels($singular, $plural),
        ];

        return new PostTypeConfiguration($slug, $singular, $plural, $initialArgs);
    }

    /**
     * Process all class-level attributes to build post type arguments.
     *
     * Scans the class for all known PostType sub-attributes and processes them
     * to build the complete arguments array for WordPress post type registration.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  string  $className  The class name to process
     * @param  PostTypeConfiguration  $config  The current configuration
     * @return PostTypeConfiguration The updated configuration
     */
    private function processClassLevelAttributes(ReflectionClass $reflectionClass, string $className, PostTypeConfiguration $config): PostTypeConfiguration
    {
        try {
            foreach ($reflectionClass->getAttributes() as $attribute) {
                if (str_contains($attribute->getName(), 'Pollora\\Attributes\\PostType\\')) {
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
     * AdminCol and RegisterMetaBoxCb, building the appropriate callback configurations.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  string  $className  The class name to process
     * @param  PostTypeConfiguration  $config  The current configuration
     * @return PostTypeConfiguration The updated configuration
     */
    private function processMethodLevelAttributes(ReflectionClass $reflectionClass, string $className, PostTypeConfiguration $config): PostTypeConfiguration
    {
        try {
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes() as $attribute) {
                    // Process all method-level attributes that have a handle method
                    $this->processMethodAttribute($method, $attribute, $config);
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
     * Takes an attribute instance and applies its configuration to the post type args.
     * Uses the attribute class name to determine the appropriate processing logic.
     *
     * @param  ReflectionClass  $reflectionClass  The reflection class
     * @param  \ReflectionAttribute  $attribute  The attribute to process
     * @param  PostTypeConfiguration  $config  The current configuration
     */
    private function processClassAttribute(ReflectionClass $reflectionClass, \ReflectionAttribute $attribute, PostTypeConfiguration $config): void
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
     * Handles method-level attributes like AdminCol and RegisterMetaBoxCb by
     * creating appropriate callback configurations.
     *
     * @param  ReflectionMethod  $method  The method with the attribute
     * @param  \ReflectionAttribute  $attribute  The attribute to process
     * @param  PostTypeConfiguration  $config  The current configuration
     */
    private function processMethodAttribute(
        ReflectionMethod $method,
        \ReflectionAttribute $attribute,
        PostTypeConfiguration $config
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
     * arguments that should be merged into the post type configuration.
     *
     * @param  string  $className  The class name to process
     * @param  PostTypeConfiguration  $config  The current configuration
     */
    private function processAdditionalArgs(string $className, PostTypeConfiguration $config): void
    {
        try {
            // Try to instantiate the class
            $reflectionClass = new ReflectionClass($className);

            if ($reflectionClass->isInstantiable()) {
                // Use instance pool if available, otherwise create directly
                $instance = $this->getInstanceFromPool($className, fn () => $reflectionClass->newInstance());

                // Check if the instance has a withArgs method
                if (method_exists($instance, 'withArgs')) {
                    $additionalArgs = $instance->withArgs();

                    if (is_array($additionalArgs) && $additionalArgs !== []) {
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
     * Generate a post type slug from class name and attribute value.
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

        $slug = Str::kebab(class_basename($className));

        return substr($slug, 0, 20);
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
     * @param  string|null  $attributePlural  The plural name from the attribute
     * @param  string  $singular  The singular name
     * @return string The generated plural name
     */
    private function generatePlural(?string $attributePlural, string $singular): string
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
            'add_new' => 'Add New',
            'add_new_item' => "Add New {$singular}",
            'edit_item' => "Edit {$singular}",
            'new_item' => "New {$singular}",
            'view_item' => "View {$singular}",
            'view_items' => "View {$plural}",
            'search_items' => "Search {$plural}",
            'not_found' => "No {$plural} found",
            'not_found_in_trash' => "No {$plural} found in Trash",
            'parent_item_colon' => "Parent {$singular}:",
            'all_items' => "All {$plural}",
            'archives' => "{$singular} Archives",
            'attributes' => "{$singular} Attributes",
            'insert_into_item' => "Insert into {$singular}",
            'uploaded_to_this_item' => "Uploaded to this {$singular}",
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'post_types';
    }
}
