<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

use Pollora\Collection\Domain\Contracts\CollectionInterface;

interface ThemeDiscoveryInterface
{
    /**
     * Discover all available themes.
     */
    public function discoverThemes(): CollectionInterface;

    /**
     * Get the active theme module.
     */
    public function getActiveTheme(): ?ThemeModuleInterface;

    /**
     * Check if a theme is active.
     */
    public function isThemeActive(string $alias): bool;

    /**
     * Get theme by alias.
     */
    public function getTheme(string $alias): ?ThemeModuleInterface;

    /**
     * Get all theme paths to scan.
     */
    public function getThemePaths(): array;
}
