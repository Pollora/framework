<?php

declare(strict_types=1);

namespace Pollen\Auth;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use WpStarter\Auth\AuthManager;

class AuthServiceProvider extends ServiceProvider
{
    public function register()
    {

    }

    public function boot()
    {
        if ($this->app->bound('auth')) {

            $authManager = $this->app->make('auth');
            /**
             * @var AuthManager $authManager
             */
            $authManager->extend('wp', function ($app, $name, $config) use ($authManager) {
                $provider = $authManager->createUserProvider($config['provider'] ?? null);

                return new WordPressGuard($provider);
            });
            $authManager->provider('wp', function ($app, $config) {
                return new WordPressUserProvider($config['model']);
            });
            if (function_exists('user_can')) {
                Gate::after(function ($user, $ability, $result, $arguments) {
                    return user_can($user, $ability, ...$arguments);
                });
            }
        }
    }
}
