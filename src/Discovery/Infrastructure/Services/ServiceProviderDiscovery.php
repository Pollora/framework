<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Services;

use Illuminate\Support\ServiceProvider;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Service Provider Discovery
 *
 * Discovers classes that extend Laravel's ServiceProvider base class
 * and collects them for automatic registration with the application.
 * This enables modules to have their service providers automatically
 * discovered and registered without manual configuration.
 *
 * @package Pollora\Discovery\Infrastructure\Services
 */
final class ServiceProviderDiscovery implements DiscoveryInterface
{
    use IsDiscovery;

    /**
     * {@inheritDoc}
     *
     * Discovers classes that extend ServiceProvider and collects them for registration.
     * Only processes concrete classes that extend Laravel's ServiceProvider.
     */
    public function discover(DiscoveryLocationInterface $location, DiscoveredStructure $structure): void
    {
        // Only process classes
        if (!$structure instanceof \Spatie\StructureDiscoverer\Data\DiscoveredClass) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        // Check if class extends ServiceProvider
        if (!$this->extendsServiceProvider($structure)) {
            return;
        }

        // Collect the class for registration
        $this->getItems()->add($location, [
            'class' => $structure->namespace . '\\' . $structure->name,
            'structure' => $structure,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Applies discovered ServiceProvider classes by registering them with the application.
     * Each discovered service provider is registered with Laravel's service container.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $discoveredItem) {
            ['class' => $className] = $discoveredItem;

            try {
                $this->registerServiceProvider($className);
            } catch (\Throwable $e) {
                // Log the error but continue with other service providers
                error_log("Failed to register service provider {$className}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if a discovered class extends ServiceProvider
     *
     * @param \Spatie\StructureDiscoverer\Data\DiscoveredClass $structure
     * @return bool
     */
    private function extendsServiceProvider(\Spatie\StructureDiscoverer\Data\DiscoveredClass $structure): bool
    {
        // Check if the class extends ServiceProvider directly
        if ($structure->extends === ServiceProvider::class) {
            return true;
        }

        // Check if it extends any class that extends ServiceProvider
        $fullClassName = $structure->namespace . '\\' . $structure->name;
        
        try {
            if (class_exists($fullClassName)) {
                return is_subclass_of($fullClassName, ServiceProvider::class);
            }
        } catch (\Throwable $e) {
            // If class cannot be loaded, skip it
            return false;
        }

        return false;
    }

    /**
     * Register a service provider with the application
     *
     * @param string $className The fully qualified class name
     * @return void
     */
    private function registerServiceProvider(string $className): void
    {
        try {
            // Only register if not already registered
            if (!$this->isServiceProviderRegistered($className)) {
                app()->register($className);
            }
        } catch (\Throwable $e) {
            error_log("Failed to register service provider {$className}: " . $e->getMessage());
        }
    }

    /**
     * Check if a service provider is already registered
     *
     * @param string $className The service provider class name
     * @return bool
     */
    private function isServiceProviderRegistered(string $className): bool
    {
        $loadedProviders = app()->getLoadedProviders();
        return isset($loadedProviders[$className]);
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return 'service_providers';
    }
}