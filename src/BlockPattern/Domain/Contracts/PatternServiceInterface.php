<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

/**
 * Port interface for pattern service.
 *
 * This is a primary port in hexagonal architecture that defines
 * how the application can interact with the block pattern domain.
 */
interface PatternServiceInterface
{
    /**
     * Register all available patterns and pattern categories.
     *
     * This method will scan for pattern files, extract data,
     * and register both the pattern categories and patterns
     * with the underlying system.
     */
    public function registerAll(): void;
}
