<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Ajax\Application\Services\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Contracts\AjaxActionRegistrarInterface;
use Pollora\Ajax\Infrastructure\Repositories\WordPressAjaxActionRegistrar;
use Pollora\Ajax\Infrastructure\Services\AjaxFactory;
use Pollora\Ajax\Infrastructure\Services\ScriptInjectionService;
use Pollora\Hook\Infrastructure\Services\Action;

class AjaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AjaxActionRegistrarInterface::class, WordPressAjaxActionRegistrar::class);
        $this->app->singleton(RegisterAjaxActionService::class, fn ($app): \Pollora\Ajax\Application\Services\RegisterAjaxActionService => new RegisterAjaxActionService($app->make(AjaxActionRegistrarInterface::class)));
        $this->app->singleton('wp.ajax', fn ($app): \Pollora\Ajax\Infrastructure\Services\AjaxFactory => new AjaxFactory($app->make(RegisterAjaxActionService::class)));
        $this->app->singleton(ScriptInjectionService::class, fn ($app): \Pollora\Ajax\Infrastructure\Services\ScriptInjectionService => new ScriptInjectionService($app->make(Action::class)));
    }

    public function boot(): void
    {
        $this->app->get(ScriptInjectionService::class)->registerAjaxUrlScript();
    }
}
