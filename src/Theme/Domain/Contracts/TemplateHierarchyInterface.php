<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Interface for template hierarchy management in themes.
 *
 * This defines the contract for handling template hierarchies
 * without coupling to WordPress or Laravel.
 */
interface TemplateHierarchyInterface
{
    /**
     * Register a custom template handler.
     *
     * @param  string  $type  Template type identifier
     * @param  callable  $callback  Function that returns an array of template files
     */
    public function registerTemplateHandler(string $type, callable $callback): void;

    /**
     * Get the template hierarchy for the current request.
     *
     * @param  bool  $refresh  Force recomputing the hierarchy even if already calculated
     * @return string[] The template hierarchy
     */
    public function hierarchy(bool $refresh = false): array;

    /**
     * Get the WordPress template hierarchy order from most specific to least specific.
     *
     * @return string[] Array of conditional function names in order of specificity
     */
    public function getHierarchyOrder(): array;
}
