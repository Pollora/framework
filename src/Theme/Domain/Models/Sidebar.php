<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Services\Translater;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Psr\Container\ContainerInterface;

/**
 * Class Sidebar
 *
 * This class is responsible for registering theme sidebars.
 */
class Sidebar implements ThemeComponent
{
    protected ContainerInterface $app;

    protected Action $action;

    protected ConfigRepositoryInterface $config;

    public function __construct(ContainerInterface $app, ConfigRepositoryInterface $config)
    {
        $this->app = $app;
        $this->action = $this->app->get(Action::class);
        $this->config = $config;
    }

    public function register(): void
    {
        $this->action->add('widgets_init', $this->registerSidebars(...), 1);
    }

    /**
     * Register all of the site's theme sidebars.
     */
    public function registerSidebars(): void
    {
        $sidebars = (array) ThemeConfig::get('sidebars', []);
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        foreach ($sidebars as $value) {
            \register_sidebar($value);
        }
    }
}
