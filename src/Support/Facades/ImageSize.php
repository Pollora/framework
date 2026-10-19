<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Media\Application\Services\ImageSizeDeclaration;
use Pollora\Media\Application\Services\ImageSizes;

/**
 * Facade for declaring the image sizes of the media library.
 *
 * A size has a name and a fit (`cover()`, `scale()`, `contain()`); its file is written in WebP by
 * default, and its name carries a signature, so the URL of a size is worked out from the
 * metadata of the original and never asks the disk. Sizes WordPress registers itself
 * (`add_image_size()`, `config/images.php` of a theme) are left as they are.
 *
 * ```php
 * ImageSize::add('card')->cover(600, 400)->webp(75);
 * ImageSize::add('hero')->scale(width: 1920)->webp(70)->widths([640, 1024, 1440]);
 * ```
 *
 * @method static ImageSizeDeclaration add(string $name) Declare a size
 *
 * @see ImageSizes
 */
class ImageSize extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ImageSizes::class;
    }
}
