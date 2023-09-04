<?php

declare(strict_types=1);

namespace Pollen\Auth;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->bound('auth')) {
            $this->registerWordPressAuthDriver();
        }
    }

    protected function registerWordPressAuthDriver(): void
    {
        $authManager = $this->app->make('auth');

        $authManager->extend('wp', function ($app, $name, $config) use ($authManager) {
            return $this->createWordPressGuard($authManager, $config);
        });

        $authManager->provider('wp', function ($app, $config) {
            return new WordPressUserProvider($config['model']);
        });

        $this->registerWordPressGate();
    }

    protected function createWordPressGuard($authManager, array $config): WordPressGuard
    {
        $provider = $authManager->createUserProvider($config['provider'] ?? null);

        return new WordPressGuard($provider);
    }

    protected function registerWordPressGate(): void
    {
        if (function_exists('user_can')) {
            Gate::after(function ($user, $ability, $result, $arguments) {
                return user_can($user, $ability, ...$arguments);
            });
        }
    }
}
