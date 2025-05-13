<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\UI;

use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeComponent;

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
    public function __construct(ServiceLocator $locator)
    {
        $this->registrationService = $locator->resolve(PatternServiceInterface::class);
        $this->action = $locator->resolve(Action::class);
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