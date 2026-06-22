<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pollora\Ajax\Adapter\Out\WordPress\ScriptInjectionAdapter;
use Pollora\Ajax\Adapter\Out\WordPress\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;

class AjaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AjaxActionRegistrarPort::class, WordPressAjaxActionRegistrar::class);
        $this->app->singleton(RegisterAjaxActionService::class, fn (Application $app): RegisterAjaxActionService => new RegisterAjaxActionService($app->make(AjaxActionRegistrarPort::class)));
        $this->app->singleton('wp.ajax', fn (Application $app): AjaxFactory => new AjaxFactory($app->make(RegisterAjaxActionService::class)));
    }

    public function boot(): void
    {
        (new ScriptInjectionAdapter)->registerAjaxUrlScript();
    }
}
