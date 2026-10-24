<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Contracts;

/**
 * Registers the features a theme declares in its configuration with the CMS.
 *
 * Menus, sidebars and image sizes are read from the theme config by the
 * domain; handing them to WordPress is the platform's business. Keeping the
 * calls behind this port lets the theme components run, and be tested,
 * without WordPress loaded.
 */
interface ThemeFeatureRegistrarInterface
{
    /**
     * Register navigation menu locations.
     *
     * @param  array<string, string>  $locations  Menu location slugs and their labels
     */
    public function registerNavMenus(array $locations): void;

    /**
     * Register a sidebar (widget area).
     *
     * @param  array<string, mixed>|string  $args  The sidebar arguments, as WordPress takes them
     */
    public function registerSidebar(array|string $args): void;

    /**
     * Register an image size.
     *
     * The dimensions and the crop flag are passed through as the theme config
     * declares them, so WordPress keeps interpreting them as it always has.
     */
    public function addImageSize(int|string $name, mixed $width = 0, mixed $height = 0, mixed $crop = false): void;
}
