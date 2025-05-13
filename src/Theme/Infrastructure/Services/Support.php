<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;

/**
 * Class Support
 *
 * The Support class is responsible for registering all of the site's theme support.
 */
class Support implements ThemeComponent
{
    protected Application $app;

    protected Action $action;

    public function __construct(ServiceLocator $locator)
    {
        $this->app = $locator->resolve(Application::class);
        $this->action = $locator->resolve(Action::class);
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
        collect(config('theme.supports'))->each(function ($value, $key): void {
            if (is_string($key)) {
                add_theme_support($key, $value);
            } else {
                add_theme_support($value);
            }
        });
    }
}
