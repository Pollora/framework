<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Theme\Domain\Contracts\ThemeFeatureRegistrarInterface;

/**
 * Registers theme menus, sidebars and image sizes with WordPress.
 */
class WordPressThemeFeatureRegistrar implements ThemeFeatureRegistrarInterface
{
    public function registerNavMenus(array $locations): void
    {
        \register_nav_menus($locations);
    }

    public function registerSidebar(array|string $args): void
    {
        \register_sidebar($args);
    }

    public function addImageSize(int|string $name, mixed $width = 0, mixed $height = 0, mixed $crop = false): void
    {
        \add_image_size((string) $name, $width, $height, $crop);
    }
}
