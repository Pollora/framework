<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\UI;

use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;
use Psr\Container\ContainerInterface;

/**
 * ThemeComponent for registering Gutenberg patterns and categories.
 *
 * Hooks into WordPress to orchestrate registration via the application service.
 */
class PatternComponent implements ThemeComponent
{
    protected PatternServiceInterface $registrationService;

    protected Action $action;

    /**
     * PatternComponent constructor.
     */
    public function __construct(protected ContainerInterface $app)
    {
        $this->registrationService = $this->app->get(PatternServiceInterface::class);
        $this->action = $this->app->get(Action::class);
    }

    /**
     * Register pattern functionality with WordPress.
     *
     * Hooks into WordPress 'init' action to register patterns and categories,
     * but skips registration during WordPress installation.
     */
    public function register(): void
    {
        $this->action->add('init', function (): void {
            if (defined('WP_INSTALLING') && WP_INSTALLING) {
                return;
            }
            $this->registrationService->registerAll();
        });
    }
}
