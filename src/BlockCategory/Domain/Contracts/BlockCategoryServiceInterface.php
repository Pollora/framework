<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Domain\Contracts;

/**
 * Port interface for block category service.
 *
 * This is a primary port in hexagonal architecture that defines
 * how the application can interact with the block category domain.
 */
interface BlockCategoryServiceInterface
{
    /**
     * Register all configured block categories.
     *
     * This method will read the configuration, transform it into
     * domain objects, and register them with the system.
     */
    public function registerConfiguredCategories(): void;
} 