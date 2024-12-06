<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->bound('auth')) {
            $this->registerWordPressAuthDriver($this->app->make(AuthManager::class));
        }
    }

    protected function registerWordPressAuthDriver(AuthManager $auth): void
    {
        $auth->extend('wp', fn ($app, $name, $config): WordPressGuard => $this->createWordPressGuard($auth, $config));

        $auth->provider('wp', fn ($app, $config): WordPressUserProvider => new WordPressUserProvider);

        $this->registerWordPressGate();
    }

    protected function createWordPressGuard(AuthManager $auth, array $config): WordPressGuard
    {
        $provider = $auth->createUserProvider($config['provider'] ?? null);

        return new WordPressGuard($provider);
    }

    protected function registerWordPressGate(): void
    {
        if (function_exists('user_can')) {
            Gate::after(fn ($user, $ability, $result, $arguments) => user_can($user, $ability, ...$arguments));
        }
    }
}
