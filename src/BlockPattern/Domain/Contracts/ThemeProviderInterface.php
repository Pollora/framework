<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

/**
 * Port interface for providing theme information.
 *
 * This is a port in hexagonal architecture that defines how
 * the domain gets information about active themes.
 */
interface ThemeProviderInterface
{
    /**
     * Get all active themes.
     *
     * @return array<object> Array of active theme objects
     */
    public function getActiveThemes(): array;
} 