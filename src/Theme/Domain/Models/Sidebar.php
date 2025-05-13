<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Services\Translater;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;

/**
 * Class Sidebar
 *
 * This class is responsible for registering theme sidebars.
 */
class Sidebar implements ThemeComponent
{
    protected Application $app;
    protected Action $action;
    protected ConfigRepositoryInterface $config;

    public function __construct(ServiceLocator $locator, ConfigRepositoryInterface $config)
    {
        $this->app = $locator->resolve(Application::class);
        $this->action = $locator->resolve(Action::class);
        $this->config = $config;
    }

    public function register(): void
    {
        $this->action->add('after_setup_theme', $this->registerSidebars(...), 1);
    }

    /**
     * Register all of the site's theme sidebars.
     */
    public function registerSidebars(): void
    {
        $sidebars = (array) ThemeConfig::get('theme.sidebars', []);
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        foreach ($sidebars as $value) {
            \register_sidebar($value);
        }
    }
}
