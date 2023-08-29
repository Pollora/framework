<?php

declare(strict_types=1);

/**
 * Class ImageSize
 *
 * This class is responsible for registering image sizes in a WordPress theme.
 */

namespace Pollen\Theme;

use Pollen\Support\Facades\Action;

/**
 * Represents a class for handling image sizes.
 */
class ImageSize
{
    public function init()
    {
        Action::add('after_setup_theme', [$this, 'addImageSize']);
    }

    /**
     * Register the image sizes.
     *
     * @return void
     */
    public function addImageSize()
    {
        collect(config('theme.images'))->each(function ($sizes, $name) {
            add_image_size(
                $name,
                $sizes[0] ?? 0,
                $sizes[1] ?? 0,
                $sizes[2] ?? false
            );
        });
    }
}
