<?php

declare(strict_types=1);

namespace Pollora\Config\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Services\LaravelConfigRepository;

/**
 * Service provider for Config module bindings.
 */
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register Config module bindings.
     */
    public function register(): void
    {
        $this->app->bind(ConfigRepositoryInterface::class, LaravelConfigRepository::class);
    }
} 