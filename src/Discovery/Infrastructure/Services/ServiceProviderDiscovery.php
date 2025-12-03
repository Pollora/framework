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
        if (! $structure instanceof \Spatie\StructureDiscoverer\Data\DiscoveredClass) {
            return;
        }

        // Skip abstract classes
        if ($structure->isAbstract) {
            return;
        }

        // Check if class extends ServiceProvider
        if (! $this->extendsServiceProvider($structure)) {
            return;
        }

        // Collect the class for registration
        $this->getItems()->add($location, [
            'class' => $structure->namespace.'\\'.$structure->name,
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
                error_log("Failed to register service provider {$className}: ".$e->getMessage());
            }
        }
    }

    /**
     * Check if a discovered class extends ServiceProvider
     */
    private function extendsServiceProvider(\Spatie\StructureDiscoverer\Data\DiscoveredClass $structure): bool
    {
        // Check if the class extends ServiceProvider directly
        if ($structure->extends === ServiceProvider::class) {
            return true;
        }

        // Use Spatie's discovery data first to avoid autoloading when possible
        if (! empty($structure->extendsChain)) {
            foreach ($structure->extendsChain as $parentClass) {
                if ($parentClass === ServiceProvider::class) {
                    return true;
                }
            }
        }

        // Fallback to runtime check only if absolutely necessary
        $fullClassName = $structure->namespace.'\\'.$structure->name;

        try {
            // First check if class is already loaded to avoid triggering autoloader
            if (class_exists($fullClassName, false)) {
                return is_subclass_of($fullClassName, ServiceProvider::class);
            }

            // Only try autoloading as last resort and catch any errors gracefully
            if (class_exists($fullClassName, true)) {
                return is_subclass_of($fullClassName, ServiceProvider::class);
            }
        } catch (\Throwable) {
            // If class cannot be loaded (missing dependencies), skip it silently
            return false;
        }

        return false;
    }

    /**
     * Register a service provider with the application
     *
     * @param  string  $className  The fully qualified class name
     */
    private function registerServiceProvider(string $className): void
    {
        try {
            // Only register if not already registered
            if (! $this->isServiceProviderRegistered($className)) {
                app()->register($className);
            }
        } catch (\Throwable $e) {
            error_log("Failed to register service provider {$className}: ".$e->getMessage());
        }
    }

    /**
     * Check if a service provider is already registered
     *
     * @param  string  $className  The service provider class name
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
