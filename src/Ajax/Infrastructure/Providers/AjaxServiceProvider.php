<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Ajax\Application\Services\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Infrastructure\Services\AjaxFactory;
use Pollora\Ajax\Infrastructure\Services\ScriptInjectionService;

class AjaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AjaxActionRegistrarInterface::class, fn ($app) => new WordPressAjaxActionRegistrar);
        $this->app->singleton(RegisterAjaxActionService::class, fn ($app) => new RegisterAjaxActionService($app->make(AjaxActionRegistrarInterface::class)));
        $this->app->singleton('wp.ajax', fn ($app) => new AjaxFactory($app->make(RegisterAjaxActionService::class)));
        $this->app->singleton(ScriptInjectionService::class, fn ($app) => new ScriptInjectionService);
    }

    public function boot(): void
    {
        $this->app->make(ScriptInjectionService::class)->registerAjaxUrlScript();
    }
}
