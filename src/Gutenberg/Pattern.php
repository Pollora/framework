<?php

declare(strict_types=1);

namespace Pollora\Gutenberg;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Gutenberg\Registrars\BlockCategoryRegistrar;
use Pollora\Gutenberg\Registrars\PatternCategoryRegistrar;
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
    protected PatternCategoryRegistrar $patternCategoryRegistrar;

    /**
     * The pattern registrar instance.
     */
    protected BlockCategoryRegistrar $blockCategoryRegistrar;

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
        $this->patternCategoryRegistrar = $container->make(PatternCategoryRegistrar::class);
        $this->patternRegistrar = $container->make(PatternRegistrar::class);
        $this->blockCategoryRegistrar = $container->make(BlockCategoryRegistrar::class);
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
            $this->patternCategoryRegistrar->register();
            $this->blockCategoryRegistrar->register();
            $this->patternRegistrar->register();
        });
    }
}
