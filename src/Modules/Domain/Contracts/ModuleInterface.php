<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Contracts;

interface ModuleInterface
{
    /**
     * Get module name.
     */
    public function getName(): string;

    /**
     * Get module name in lowercase.
     */
    public function getLowerName(): string;

    /**
     * Get module name in studly case.
     */
    public function getStudlyName(): string;

    /**
     * Get root module namespace in studly case.
     */
    public function getRootNamespace(): string;

    /**
     * Get module namespace in studly case.
     */
    public function getNamespace(): string;

    /**
     * Get module description.
     */
    public function getDescription(): string;

    /**
     * Get module path.
     */
    public function getPath(): string;

    /**
     * Bootstrap the module.
     */
    public function boot(): void;

    /**
     * Register the module.
     */
    public function register(): void;

    /**
     * Check if module is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Check if module is disabled.
     */
    public function isDisabled(): bool;

    /**
     * Enable the module.
     */
    public function enable(): void;

    /**
     * Disable the module.
     */
    public function disable(): void;

    /**
     * Get module metadata.
     */
    public function get(string $key, mixed $default = null): mixed;
}
