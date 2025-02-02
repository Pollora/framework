<?php

declare(strict_types=1);

namespace Pollora\Gutenberg;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Gutenberg\Registrars\CategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternRegistrar;
use Pollora\Support\Facades\Action;
use Pollora\Theme\Contracts\ThemeComponent;

/**
 * Main class for handling Gutenberg block patterns.
 *
 * Coordinates the registration of block patterns and their categories
 * by integrating with WordPress initialization process.
 */
class Pattern implements ThemeComponent
{
    /**
     * The category registrar instance.
     */
    protected CategoryRegistrar $categoryRegistrar;

    /**
     * The pattern registrar instance.
     */
    protected PatternRegistrar $patternRegistrar;

    /**
     * Create a new Pattern instance.
     *
     * Initializes registrars using the application container.
     *
     * @param  Application  $container  The application container instance
     */
    public function __construct(Application $container)
    {
        $this->categoryRegistrar = $container->make(CategoryRegistrar::class);
        $this->patternRegistrar = $container->make(PatternRegistrar::class);
    }

    /**
     * Register pattern functionality with WordPress.
     *
     * Hooks into WordPress 'init' action to register patterns and categories,
     * but skips registration during WordPress installation.
     */
    public function register(): void
    {
        Action::add('init', function (): void {
            if (wp_installing()) {
                return;
            }
            $this->categoryRegistrar->register();
            $this->patternRegistrar->register();
        });
    }
}
