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
 * including custom guards and user providers. It integrates WordPress
 * capabilities with Laravel's Gate system and handles the registration
 * of authentication-related services in the container.
 *
 * @extends ServiceProvider
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register authentication services.
     * 
     * Registers the WordPress authentication driver if the auth service is bound.
     * This includes setting up the WordPress guard and user provider.
     *
     * @return void
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
     * @return void
     */
    protected function registerWordPressAuthDriver(AuthManager $auth): void
    {
        $auth->extend('wp', fn ($app, $name, $config): WordPressGuard => 
            $this->createWordPressGuard($auth, $config));

        $auth->provider('wp', fn ($app, $config): WordPressUserProvider => 
            new WordPressUserProvider);

        $this->registerWordPressGate();
    }

    /**
     * Create a new WordPress guard instance.
     * 
     * Configures and returns a new WordPress guard with the appropriate
     * user provider based on the configuration.
     *
     * @param AuthManager $auth   The Laravel authentication manager
     * @param array<string, mixed> $config Guard configuration options
     * @return WordPressGuard The configured WordPress guard instance
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
     * This method is only activated if the WordPress user_can function exists.
     *
     * @return void
     */
    protected function registerWordPressGate(): void
    {
        if (function_exists('user_can')) {
            Gate::after(fn ($user, $ability, $result, $arguments) => 
                user_can($user, $ability, ...$arguments));
        }
    }
}
