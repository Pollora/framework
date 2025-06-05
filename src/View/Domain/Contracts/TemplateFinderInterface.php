<?php

declare(strict_types=1);

namespace Pollora\View\Domain\Contracts;

/**
 * Contract for finding template files in the file system.
 *
 * This interface defines the core capability to locate template files
 * based on template names, following WordPress template hierarchy rules.
 */
interface TemplateFinderInterface
{
    /**
     * Locate template files from a list of template names.
     *
     * @param  array<string>|string  $templateNames  Template names to locate
     * @return array<string> Located template paths
     */
    public function locate($templateNames): array;

    /**
     * Check if a template exists.
     */
    public function exists(string $templateName): bool;

    /**
     * Get view name from a template file path.
     *
     * Converts a file path to a Laravel view name that can be used with view().
     */
    public function getViewNameFromPath(string $filePath): ?string;

    /**
     * Convert template names to their Blade equivalents.
     *
     * @param  array<string>  $templates
     * @return array<string>
     */
    public function getBladeTemplates(array $templates): array;
}
