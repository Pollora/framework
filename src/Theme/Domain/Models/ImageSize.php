<?php

declare(strict_types=1);

/**
 * Class ImageSize
 *
 * This class is responsible for registering image sizes in a WordPress theme.
 */

namespace Pollora\Theme\Domain\Models;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Psr\Container\ContainerInterface;

/**
 * Represents a class for handling image sizes.
 */
class ImageSize implements ThemeComponent
{
    protected Action $action;

    public function __construct(
        protected ContainerInterface $app,
        protected ConfigRepositoryInterface $config
    ) {
        $this->action = $this->app->get(Action::class);
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
        $images = ThemeConfig::get('theme.images', []);

        foreach ($images as $name => $sizes) {
            \add_image_size(
                $name,
                $sizes[0] ?? 0,
                $sizes[1] ?? 0,
                $sizes[2] ?? false
            );
        }
    }
}
