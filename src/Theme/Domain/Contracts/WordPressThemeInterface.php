<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Interface for WordPress theme-related functionality.
 *
 * This interface abstracts WordPress core theme functions from the domain layer
 * to maintain proper hexagonal architecture separation.
 */
interface WordPressThemeInterface
{
    /**
     * Check if WordPress is in installation mode.
     */
    public function isInstalling(): bool;

    /**
     * Register a theme directory with WordPress.
     */
    public function registerThemeDirectory(string $path): bool;

    /**
     * Get the current active theme's stylesheet name.
     */
    public function getStylesheet(): string;

    /**
     * Get the current parent theme's template name.
     */
    public function getTemplate(): string;

    /**
     * Get a WP_Theme instance.
     */
    public function getTheme(?string $themeName = null): object;

    /**
     * Get WordPress content directories.
     */
    public function getThemeDirectories(): array;

    /**
     * Get stylesheet directory URI.
     */
    public function getStylesheetDirectoryUri(): string;
}
