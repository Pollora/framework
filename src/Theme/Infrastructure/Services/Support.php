<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Psr\Container\ContainerInterface;

/**
 * Class Support
 *
 * The Support class is responsible for registering all of the site's theme support.
 */
class Support implements ThemeComponent
{
    protected Action $action;

    public function __construct(protected ContainerInterface $app, protected ConfigRepositoryInterface $config)
    {
        $this->action = $this->app->get(Action::class);
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
