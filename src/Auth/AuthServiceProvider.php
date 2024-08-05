<?php

declare(strict_types=1);

namespace Pollen\Auth;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->bound('auth')) {
            $this->registerWordPressAuthDriver();
        }
    }

    protected function registerWordPressAuthDriver(): void
    {
        $this->app['auth']->extend('wp', fn($app, $name, $config) =>
        $this->createWordPressGuard($config)
        );

        $this->app['auth']->provider('wp', fn($app, $config) =>
            new WordPressUserProvider()
        );

        $this->registerWordPressGate();
    }

    protected function createWordPressGuard(array $config): WordPressGuard
    {
        $provider = $this->app['auth']->createUserProvider($config['provider'] ?? null);
        return new WordPressGuard($provider);
    }

    protected function registerWordPressGate(): void
    {
        if (function_exists('user_can')) {
            Gate::after(fn($user, $ability, $result, $arguments) =>
            user_can($user, $ability, ...$arguments)
            );
        }
    }
}
