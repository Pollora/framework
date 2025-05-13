<?php

declare(strict_types=1);

/**
 * Class ImageSize
 *
 * This class is responsible for registering image sizes in a WordPress theme.
 */

namespace Pollora\Theme\Domain\Models;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;

/**
 * Represents a class for handling image sizes.
 */
class ImageSize implements ThemeComponent
{
    protected Action $action;
    protected ConfigRepositoryInterface $config;

    public function __construct(
        ServiceLocator $locator, 
        ConfigRepositoryInterface $config
    ) {
        $this->action = $locator->resolve(Action::class);
        $this->config = $config;
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
