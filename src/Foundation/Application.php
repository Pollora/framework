<?php

declare(strict_types=1);

namespace Pollora\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Pollora\Route\RouteServiceProvider;

/**
 * Custom Laravel Application class for WordPress integration.
 *
 * Extends Laravel's base Application class to provide WordPress-specific
 * service provider registration and configuration.
 */
class Application extends \Illuminate\Foundation\Application
{
    /**
     * Register the basic service providers.
     *
     * Registers core service providers required for WordPress integration,
     * including events, logging, and routing functionality.
     */
    public function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new RouteServiceProvider($this));
    }

    /**
     * Determine if the application is running in the WP-CLI environment.
     *
     * This checks whether the WP_CLI constant is defined and evaluates to true,
     * indicating that the application is being executed within the WordPress CLI.
     *
     * @return bool True if running in WP-CLI, false otherwise.
     */
    public function runningInWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }
}
