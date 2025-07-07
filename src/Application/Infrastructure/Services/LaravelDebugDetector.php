<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Services;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;

/**
 * Laravel implementation for detecting debug mode.
 */
class LaravelDebugDetector implements DebugDetectorInterface
{
    /**
     * Constructor.
     *
     * @param  Application  $app  The Laravel application instance
     */
    public function __construct(private Application $app) {}

    /**
     * Determine if the application is in debug mode.
     * Checks the 'debug' configuration value for Laravel environments.
     *
     * @return bool True if application is in debug mode
     */
    public function isDebugMode(): bool
    {
        return $this->app['config']->get('app.debug', false);
    }
}
