<?php

declare(strict_types=1);

namespace Pollen\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Register our WordPress guard with Laravel.
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Auth::extend('wordpress', function ($app, $name, array $config) {
            return new WordPressGuard(Auth::createUserProvider($config['provider']));
        });
    }
}
