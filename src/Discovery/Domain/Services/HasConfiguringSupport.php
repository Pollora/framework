<?php

declare(strict_types=1);

namespace Pollora\Discovery\Domain\Services;

/**
 * Trait providing support for the configuring lifecycle hook.
 *
 * This trait can be used by discovery services to call an optional
 * configuring method on discovered classes before registration.
 *
 * Classes using this trait must:
 * - Use the HasInstancePool trait
 * - Implement ConfigurableDiscoveryInterface
 */
trait HasConfiguringSupport
{
    /**
     * Call the configuring method on the class instance if it exists.
     *
     * This method is called just before registration, allowing the class
     * to perform additional configuration using the entity instance.
     *
     * @param  string  $className  The fully qualified class name to process
     * @param  string  $slug  The entity slug
     * @param  string|null  $singular  The singular name
     * @param  string|null  $plural  The plural name
     * @param  array  $args  Additional arguments
     * @param  int  $priority  Declaration priority
     * @param  \Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface|null  $reflectionCache  Optional reflection cache
     * @return object|null The entity if configuring was called, null otherwise
     */
    protected function processConfiguring(string $className, string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5, ?\Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface $reflectionCache = null): ?object
    {
        try {
            $reflectionClass = $reflectionCache->getClassReflection($className);

            if (! $reflectionClass->isInstantiable()) {
                return null;
            }

            // Réutiliser l'instance du pool pour cohérence avec withArgs
            $instance = $this->getInstanceFromPool(
                $className,
                fn (): object => $reflectionClass->newInstance()
            );

            if (! method_exists($instance, 'configuring')) {
                return null;
            }

            // Créer l'entity pour la configuration
            $entity = $this->createEntityForConfiguring($slug, $singular, $plural, $args, $priority);

            // Appeler configuring avec l'entity
            $instance->configuring($entity);

            return $entity;

        } catch (\ReflectionException|\Throwable $e) {
            error_log(
                "Failed to process configuring for {$className}: ".$e->getMessage()
            );

            return null;
        }
    }

    /**
     * Apply smart merge logic for entity and attribute configurations.
     *
     * This method merges configurations from the configuring() method with
     * attribute-based configurations, giving priority to configuring() values.
     *
     * @param  object  $configuredEntity  The entity configured via configuring()
     * @param  array  $attributeArgs  Arguments from attribute processing
     */
    protected function applySmartMerge(object $configuredEntity, array $attributeArgs): void
    {
        // Get the args built from entity properties via ArgumentHelper
        $entityArgs = $configuredEntity->getArgs() ?? [];

        // For labels, do a smart merge: use configuring labels as base, add missing ones from attributes
        if (isset($entityArgs['labels']) && isset($attributeArgs['labels'])) {
            $attributeArgs['labels'] = array_merge($attributeArgs['labels'], $entityArgs['labels']);
        }

        // Merge other args with configuring() taking priority
        $finalArgs = array_merge($attributeArgs, $entityArgs);

        $configuredEntity->setRawArgs($finalArgs);
    }

    /**
     * Register an entity using the appropriate registry adapter.
     *
     * @param  object  $entity  The entity to register
     * @param  string  $registryClass  The registry adapter class
     */
    protected function registerEntity(object $entity, string $registryClass): void
    {
        $registry = new $registryClass;
        $registrationService = new \Pollora\Entity\Application\Service\EntityRegistrationService($registry);
        $registrationService->registerEntity($entity);
    }
}
