<?php

declare(strict_types=1);

namespace Pollora\PostType\Infrastructure\Services;

use Illuminate\Support\Str;
use Pollora\Attributes\Contracts\ConfiguresArguments;
use Pollora\Attributes\Contracts\ConfiguresArgumentsWithMethod;
use Pollora\Attributes\Contracts\HandlesAttributes;
use Pollora\Attributes\PostType;
use Pollora\Discovery\Domain\Contracts\ConfigurableDiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Services\HasConfiguringSupport;
use Pollora\Discovery\Domain\Services\HasInstancePool;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Pollora\Entity\Adapter\Out\WordPress\PostTypeRegistryAdapter;
use Pollora\Entity\Domain\Model\PostType as EntityPostType;
use Pollora\PostType\Domain\Contracts\PostTypeServiceInterface;
use Pollora\Translation\Infrastructure\Adapters\TranslatingEntityRegistry;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
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
final class PostTypeDiscovery implements ConfigurableDiscoveryInterface, DiscoveryInterface
{
    use HasConfiguringSupport;
    use HasInstancePool;
    use IsDiscovery;

    /**
     * Create a new PostType discovery service.
     *
     * @param  PostTypeServiceInterface  $postTypeService  The post type service for registration
     */
    public function __construct(
        private readonly PostTypeServiceInterface $postTypeService,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function createEntityForConfiguring(string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5): EntityPostType
    {
        // Generate singular name if not provided
        $singular ??= $this->generateSingular($slug, null);

        // Generate plural name if not provided
        $plural ??= Str::plural($singular);

        // Create the Entity PostType instance directly without auto-registration
        $postType = new EntityPostType($slug, $singular, $plural);
        $postType->init();
        $postType->priority($priority);

        // Apply additional arguments if provided
        if ($args !== []) {
            $postType->setRawArgs($args);
        }

        return $postType;
    }

    /**
     * {@inheritDoc}
     *
     * Discovers classes with PostType attributes and collects them for registration.
     * Only processes classes that have the PostType attribute and are instantiable.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure, ?ReflectionCacheInterface $reflectionCache = null): void
    {
        // Only process classes
        if (! $structure instanceof DiscoveredClass) {
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
            'reflection_cache' => $reflectionCache,
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
                $reflectionCache = $discoveredItem['reflection_cache'] ?? null;
                $this->processPostType($className, $reflectionCache);
            } catch (\Throwable $e) {
                // Log the error but continue with other post types
                $this->logger?->error(sprintf('Failed to register PostType from class %s', $className), ['exception' => $e]);
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
     * @param  ReflectionCacheInterface|null  $reflectionCache  Optional reflection cache
     */
    private function processPostType(string $className, ?ReflectionCacheInterface $reflectionCache = null): void
    {
        try {
            $reflectionClass = $reflectionCache->getClassReflection($className);
            $postTypeAttributes = $reflectionCache->getClassAttributes($className, PostType::class);

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
            $config = $this->processMethodLevelAttributes($className, $config, $reflectionCache);

            // Get additional arguments from the class instance if it has a withArgs method
            $this->processAdditionalArgs($className, $config, $reflectionCache);

            // Call configuring method if present and use the configured entity
            $configuredEntity = $this->processConfiguring(
                $className,
                $config->getSlug(),
                $config->getName(),
                $config->getPluralName(),
                [], // Pass empty args initially, we'll apply attribute configs after
                $config->getPriority(),
                $reflectionCache
            );

            // If configuring was called and returned an entity, apply attribute configurations and register
            if ($configuredEntity !== null) {
                $this->applySmartMerge($configuredEntity, $config->getArgs());
                $this->registerEntity($configuredEntity, PostTypeRegistryAdapter::class);
            } else {
                // Register the post type using the original service
                $this->postTypeService->register(
                    $config->getSlug(),
                    $config->getName(),
                    $config->getPluralName(),
                    $config->getArgs(),
                    $config->getPriority()
                );
            }

        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to process PostType for class %s', $className), ['exception' => $throwable]);
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
        $slug = $postType->resolveSlug($className);
        $singular = $this->generateSingular($className, $postType->singular);
        $plural = $this->generatePlural($postType->plural, $singular);
        $textDomain = $postType->textDomain ?? 'pollora';

        $initialArgs = [
            'labels' => TranslatingEntityRegistry::defaultLabels(TranslatingEntityRegistry::POST_TYPE, $singular, $plural),
            'text_domain' => $textDomain,
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
        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to process class-level attributes for %s', $className), ['exception' => $throwable]);
        }

        return $config;
    }

    /**
     * Process method-level attributes to build callback configurations.
     *
     * Scans all public methods of the class for method-level attributes like
     * AdminCol and RegisterMetaBoxCb, building the appropriate callback configurations.
     *
     * @param  string  $className  The class name to process
     * @param  PostTypeConfiguration  $config  The current configuration
     * @param  ReflectionCacheInterface|null  $reflectionCache  Optional reflection cache
     * @return PostTypeConfiguration The updated configuration
     */
    private function processMethodLevelAttributes(string $className, PostTypeConfiguration $config, ?ReflectionCacheInterface $reflectionCache = null): PostTypeConfiguration
    {
        try {
            foreach ($reflectionCache->getPublicMethods($className) as $method) {
                foreach ($method->getAttributes() as $attribute) {
                    // Process all method-level attributes that have a handle method
                    $this->processMethodAttribute($method, $attribute, $config);
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger?->error(sprintf('Failed to process method-level attributes for %s', $className), ['exception' => $throwable]);
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

        if ($attributeInstance instanceof ConfiguresArguments) {
            $config->replaceArgs($attributeInstance->configureArguments($config->withArgs()));

            return;
        }

        if ($attributeInstance instanceof HandlesAttributes) {
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

        if ($attributeInstance instanceof ConfiguresArgumentsWithMethod) {
            $config->replaceArgs($attributeInstance->configureArguments($config->withArgs(), [new $method->class, $method->getName()]));

            return;
        }

        if ($attributeInstance instanceof HandlesAttributes) {
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
     * @param  ReflectionCacheInterface|null  $reflectionCache  Optional reflection cache
     */
    private function processAdditionalArgs(string $className, PostTypeConfiguration $config, ?ReflectionCacheInterface $reflectionCache = null): void
    {
        try {
            $reflectionClass = $reflectionCache->getClassReflection($className);

            if ($reflectionClass->isInstantiable()) {
                // Use instance pool if available, otherwise create directly
                $instance = $this->getInstanceFromPool($className, fn (): object => $reflectionClass->newInstance());

                // Check if the instance has a withArgs method
                if (method_exists($instance, 'withArgs')) {
                    $additionalArgs = $instance->withArgs();

                    if (is_array($additionalArgs) && $additionalArgs !== []) {
                        $config->mergeArgs($additionalArgs);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            // Log the error but continue - additional args are optional
            $this->logger?->error(sprintf('Failed to process additional args for %s', $className), ['exception' => $throwable]);
        }
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
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'post_types';
    }
}
