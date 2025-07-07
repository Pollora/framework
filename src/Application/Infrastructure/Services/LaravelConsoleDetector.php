<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Services;

use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;

/**
 * Laravel-specific implementation for console detection.
 */
class LaravelConsoleDetector implements ConsoleDetectorInterface
{
    /**
     * Constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct(
        /**
         * The Laravel application instance.
         */
        protected $app
    ) {}

    /**
     * {@inheritdoc}
     */
    public function isConsole(): bool
    {
        return $this->app->runningInConsole();
    }

    /**
     * {@inheritdoc}
     */
    public function isWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }
}
