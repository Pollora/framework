<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for WordPress authentication integration.
 *
 * This provider registers and configures WordPress authentication services,
 * including custom guards and user providers, and integrates WordPress
 * capabilities with Laravel's Gate system.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register authentication services.
     *
     * Registers the WordPress authentication driver if the auth service is bound.
     */
    public function register(): void
    {
        if ($this->app->bound('auth')) {
            $this->registerWordPressAuthDriver($this->app->make(AuthManager::class));
        }
    }

    /**
     * Register the WordPress authentication driver.
     *
     * Sets up the WordPress guard and user provider, and configures
     * the WordPress capabilities gate.
     *
     * @param AuthManager $auth The Laravel authentication manager
     */
    protected function registerWordPressAuthDriver(AuthManager $auth): void
    {
        $auth->extend('wp', fn ($app, $name, $config): WordPressGuard => $this->createWordPressGuard($auth, $config));

        $auth->provider('wp', fn ($app, $config): WordPressUserProvider => new WordPressUserProvider);

        $this->registerWordPressGate();
    }

    /**
     * Create a new WordPress guard instance.
     *
     * @param AuthManager $auth The Laravel authentication manager
     * @param array $config Guard configuration
     * @return WordPressGuard The configured WordPress guard
     */
    protected function createWordPressGuard(AuthManager $auth, array $config): WordPressGuard
    {
        $provider = $auth->createUserProvider($config['provider'] ?? null);

        return new WordPressGuard($provider);
    }

    /**
     * Register WordPress capabilities with Laravel's Gate.
     *
     * Integrates WordPress's user_can() function with Laravel's authorization system,
     * allowing WordPress capabilities to be checked using Laravel's Gate facade.
     */
    protected function registerWordPressGate(): void
    {
        if (function_exists('user_can')) {
            Gate::after(fn ($user, $ability, $result, $arguments) => user_can($user, $ability, ...$arguments));
        }
    }
}
