<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Theme\Domain\Models\ThemeMetadata;

/**
 * Interface for WordPress Theme management service
 */
interface ThemeService
{
    /**
     * Get the current instance
     */
    public function instance(): self;

    /**
     * Load a theme by name
     */
    public function load(string $themeName): void;

    /**
     * Get path relative to the active theme
     */
    public function path(string $path): string;

    /**
     * Get the active theme name
     */
    public function active(): ?string;

    /**
     * Get the theme metadata object
     */
    public function theme(): ?ThemeMetadata;

    /**
     * Get the parent theme name
     */
    public function parent(): ?string;

    /**
     * Get all parent themes, closest ancestor first
     *
     * @return array<int, ThemeMetadata>
     */
    public function getParentThemes(): array;

    /**
     * Get a list of all available themes
     */
    public function getAvailableThemes(): array;

    /**
     * Check if a theme is registered.
     */
    public function hasTheme(string $name): bool;

    /**
     * Get the active theme module.
     */
    public function getActiveTheme(): ?ModuleInterface;
}
