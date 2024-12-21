<?php

declare(strict_types=1);

namespace Pollora\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Pollora\Route\RouteServiceProvider;

class Application extends \Illuminate\Foundation\Application
{
    /**
     * Register the base service providers for the application.
     *
     * This method ensures that essential Laravel services (Events, Logging, Routing) are available.
     *
     * @return void
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
