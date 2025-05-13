<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Models;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Services\Translater;
use Pollora\Theme\Domain\Contracts\ThemeComponent;

/**
 * Class Sidebar
 *
 * This class is responsible for registering theme sidebars.
 */
class Sidebar implements ThemeComponent
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
        $this->action->add('after_setup_theme', $this->registerSidebars(...), 1);
    }

    /**
     * Register all of the site's theme sidebars.
     */
    public function registerSidebars(): void
    {
        $sidebars = (array) config('theme.sidebars');
        $translater = new Translater($sidebars, 'sidebars');
        $sidebars = $translater->translate(['*.name', '*.description']);

        collect($sidebars)->each(function ($value): void {
            register_sidebar($value);
        });
    }
}
