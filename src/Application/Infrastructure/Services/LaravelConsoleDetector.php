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
     * The Laravel application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

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
