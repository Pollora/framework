<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;

/**
 * Class Support
 *
 * The Support class is responsible for registering all of the site's theme support.
 */
class Support implements ThemeComponent
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
        $this->action->add('after_setup_theme', [$this, 'addThemeSupport'], 1);
    }

    /**
     * Register all of the site's theme support.
     */
    public function addThemeSupport(): void
    {
        $supports = ThemeConfig::get('theme.supports', []);
        
        foreach ($supports as $key => $value) {
            if (is_string($key)) {
                \add_theme_support($key, $value);
            } else {
                \add_theme_support($value);
            }
        }
    }
}
