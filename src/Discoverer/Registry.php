<?php

declare(strict_types=1);

namespace Pollora\Discoverer;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;

/**
 * Registry for discovered classes.
 *
 * Stores and organizes discovered classes by type for easy retrieval,
 * filtering classes belonging to disabled modules.
 */
class Registry implements DiscoveryRegistry
{
    /**
     * @var array<string, array<string>> Array of discovered classes by type
     */
    protected array $registry = [];

    /**
     * @var array<string, bool> Map of registered classes for quick lookups
     */
    protected array $classMap = [];

    /**
     * Register a class with the registry, only if it doesn't belong to a disabled module.
     *
     * @param  string  $class  Fully qualified class name
     * @param  string  $type  Type identifier for the class
     */
    public function register(string $class, string $type): void
    {
        if ($this->shouldRegisterClass($class)) {
            $this->registry[$type][] = $class;
            $this->classMap[$class] = true;
        }
    }

    /**
     * Determine if a class should be registered based on its module's status.
     *
     * @param  string  $class  Fully qualified class name
     * @return bool True if class should be registered
     */
    protected function shouldRegisterClass(string $class): bool
    {
        $moduleName = $this->extractModuleName($class);

        return ! $moduleName || app(RepositoryInterface::class)->isEnabled($moduleName);
    }

    /**
     * Extract the module name from a fully qualified class name.
     *
     * Returns the immediate namespace segment after the configured module namespace,
     * or false if the class is not within a module namespace.
     *
     * @param  string  $class  Fully qualified class name
     * @return string|false Module name or false
     */
    protected function extractModuleName(string $class): string|false
    {
        $moduleNamespace = config('modules.namespace').'\\';

        if (! str_starts_with($class, $moduleNamespace)) {
            return false;
        }

        $relative = substr($class, strlen($moduleNamespace));

        return strstr($relative, '\\', true) ?: false;
    }

    /**
     * Get all registered classes of a specific type.
     *
     * @param  string  $type  Type identifier
     * @return array<string> Array of class names
     */
    public function getByType(string $type): array
    {
        return $this->registry[$type] ?? [];
    }

    /**
     * Check if a class is registered.
     *
     * @param  string  $class  Fully qualified class name
     * @return bool True if the class is registered
     */
    public function has(string $class): bool
    {
        return isset($this->classMap[$class]);
    }

    /**
     * Get all registered classes.
     *
     * @return array<string, array<string>> Array of classes by type
     */
    public function all(): array
    {
        return $this->registry;
    }
}
