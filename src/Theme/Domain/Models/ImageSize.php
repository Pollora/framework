<?php

declare(strict_types=1);

/**
 * Class ImageSize
 *
 * This class is responsible for registering image sizes in a WordPress theme.
 */

namespace Pollora\Theme\Domain\Models;

use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;

/**
 * Represents a class for handling image sizes.
 */
class ImageSize implements ThemeComponent
{
    protected Action $action;

    public function __construct(ServiceLocator $locator)
    {
        $this->action = $locator->resolve(Action::class);
    }

    public function register(): void
    {
        $this->action->add('after_setup_theme', [$this, 'addImageSize']);
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
