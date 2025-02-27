<?php

declare(strict_types=1);

namespace Pollora\Foundation;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\Context\ContextServiceProvider;
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
        $this->register(new ContextServiceProvider($this));
        $this->register(new RouteServiceProvider($this));
    }
}
