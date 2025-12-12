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
     * @return object|null The entity if configuring was called, null otherwise
     */
    protected function processConfiguring(string $className, string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5): ?object
    {
        try {
            $reflectionClass = new \ReflectionClass($className);

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
}
