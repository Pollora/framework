<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Interface for container operations within the Theme domain.
 * 
 * This provides a clean abstraction for accessing container functionality
 * without coupling to a specific framework implementation.
 */
interface ContainerInterface extends \Psr\Container\ContainerInterface 
{
    /**
     * Register a service provider with the container.
     *
     * @param string|object $provider The provider to register
     * @return void
     */
    public function registerProvider(string|object $provider): void;
    
    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract The abstract to bind
     * @param mixed $concrete The concrete implementation
     * @return void
     */
    public function bindShared(string $abstract, mixed $concrete): void;
    
    /**
     * Check if configuration is cached.
     *
     * @return bool True if configuration is cached
     */
    public function isConfigurationCached(): bool;
    
    /**
     * Get a configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $default The default value
     * @return mixed The configuration value
     */
    public function getConfig(string $key, mixed $default = null): mixed;
    
    /**
     * Set a configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $value The value to set
     * @return void
     */
    public function setConfig(string $key, mixed $value): void;
} 