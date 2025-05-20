<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

use Pollora\BlockPattern\Domain\Models\PatternCategory;

/**
 * Port interface for registering pattern categories.
 *
 * This is a port in hexagonal architecture that defines how
 * the domain communicates with the outside world regarding
 * pattern category registration.
 */
interface PatternCategoryRegistrarInterface
{
    /**
     * Register a pattern category with the underlying system.
     *
     * @param string $slug The category slug
     * @param array<string, mixed> $attributes The category attributes
     */
    public function registerCategory(string $slug, array $attributes): void;
} 