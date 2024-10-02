<?php

declare(strict_types=1);

/**
 * Class ImageSize
 *
 * This class is responsible for registering image sizes in a WordPress theme.
 */

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;
use Pollen\Theme\Contracts\ThemeComponent;

/**
 * Represents a class for handling image sizes.
 */
class ImageSize implements ThemeComponent
{
    public function register(): void
    {
        Action::add('after_setup_theme', $this->addImageSize(...));
    }

    /**
     * Register the image sizes.
     */
    public function addImageSize(): void
    {
        collect(config('theme.images'))->each(function ($sizes, $name): void {
            add_image_size(
                $name,
                $sizes[0] ?? 0,
                $sizes[1] ?? 0,
                $sizes[2] ?? false
            );
        });
    }
}
