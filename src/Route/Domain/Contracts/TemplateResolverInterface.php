<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

use Pollora\Route\Domain\Models\TemplateHierarchy;

/**
 * Port for template hierarchy resolution
 * 
 * Handles the resolution of WordPress template hierarchy and
 * template file location.
 */
interface TemplateResolverInterface
{
    /**
     * Resolve template hierarchy from context
     * 
     * @param array $context Request context including WordPress globals
     * @return TemplateHierarchy The resolved template hierarchy
     */
    public function resolveHierarchy(array $context): TemplateHierarchy;

    /**
     * Find the first existing template from hierarchy
     * 
     * @param TemplateHierarchy $hierarchy The template hierarchy to search
     * @return string|null The path to the first found template
     */
    public function findTemplate(TemplateHierarchy $hierarchy): ?string;

    /**
     * Register a custom template handler for a specific type
     * 
     * @param string $type The template type (e.g., 'page', 'single')
     * @param callable $handler The handler function
     * @return void
     */
    public function registerTemplateHandler(string $type, callable $handler): void;

    /**
     * Check if a template exists
     * 
     * @param string $template The template name
     * @return bool True if template exists
     */
    public function templateExists(string $template): bool;

    /**
     * Get template search paths
     * 
     * @return array Array of paths where templates are searched
     */
    public function getTemplatePaths(): array;

    /**
     * Add a template search path
     * 
     * @param string $path The path to add
     * @param int $priority Priority for path ordering (higher = searched first)
     * @return void
     */
    public function addTemplatePath(string $path, int $priority = 0): void;

    /**
     * Resolve template extensions in order of preference
     * 
     * @return array Array of extensions (e.g., ['.blade.php', '.php'])
     */
    public function getTemplateExtensions(): array;

    /**
     * Set template extensions
     * 
     * @param array $extensions Array of extensions in order of preference
     * @return void
     */
    public function setTemplateExtensions(array $extensions): void;
}