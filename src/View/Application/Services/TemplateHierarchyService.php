<?php

declare(strict_types=1);

namespace Pollora\View\Application\Services;

use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;

/**
 * Main application service for template hierarchy management.
 *
 * This service orchestrates the template hierarchy integration by
 * coordinating the registration of WordPress filters and providing
 * a high-level interface for template hierarchy operations.
 */
class TemplateHierarchyService
{
    public function __construct(
        private readonly RegisterTemplateHierarchyFiltersUseCase $registerFiltersUseCase
    ) {}

    /**
     * Initialize the template hierarchy system.
     *
     * This registers all necessary WordPress filters to integrate
     * Blade templates with the WordPress template hierarchy.
     */
    public function initialize(): void
    {
        $this->registerFiltersUseCase->execute();
    }
}
