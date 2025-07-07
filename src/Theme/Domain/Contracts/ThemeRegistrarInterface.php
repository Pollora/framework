<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Interface for theme self-registration.
 *
 * This interface allows themes to register themselves as active,
 * eliminating the need for database queries to determine the active theme.
 */
interface ThemeRegistrarInterface
{
    /**
     * Register a theme as the active theme.
     *
     * @param  string  $themeName  The name of the theme
     * @param  string  $themePath  The path to the theme directory
     * @param  array  $themeData  Optional theme metadata
     * @return ThemeModuleInterface The registered theme module
     */
    public function register(): ThemeModuleInterface;

    /**
     * Get the currently registered active theme.
     *
     * @return ThemeModuleInterface|null The active theme or null if none registered
     */
    public function getActiveTheme(): ?ThemeModuleInterface;

    /**
     * Check if a theme is registered as active.
     *
     * @param  string  $themeName  The theme name to check
     * @return bool True if the theme is active
     */
    public function isThemeActive(string $themeName): bool;

    /**
     * Reset the active theme registration.
     */
    public function resetActiveTheme(): void;
}
