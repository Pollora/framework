<?php

declare(strict_types=1);

namespace Pollora\Hook\Infrastructure\Providers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Pollora\Doctor\Infrastructure\Providers\DoctorServiceProvider;
use Pollora\Hook\Async\Async;
use Pollora\Hook\Async\Contracts\AsyncDriver;
use Pollora\Hook\Infrastructure\Async\EloquentModelReference;
use Pollora\Hook\Infrastructure\Async\QueueDriver;
use Pollora\Hook\Infrastructure\Checks\AsyncActionsCheck;
use Pollora\Hook\UI\Console\AsyncListCommand;
use Psr\Log\LoggerInterface;

/**
 * Connects the asynchronous actions of pollora/hook to the application.
 *
 * - config/hooks.php (publishable with the "pollora-hooks" tag): default driver, attempts, backoff, as_user, queue
 * - the "queue" driver, first of the drivers "auto" tries once hooks.async.queue.connection is set
 * - debug mode from app.debug, incidents to the Laravel log
 * - closures signed with the application key
 * - Eloquent models carried by reference
 * - handler parameters that are not hook arguments resolved from the container
 * - the async-actions check of pollora:doctor and Site Health
 */
class AsyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../../config/hooks.php', 'hooks');

        if ($this->app->runningInConsole()) {
            $this->commands([AsyncListCommand::class]);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../../../config/hooks.php' => config_path('hooks.php'),
        ], 'pollora-hooks');

        // A check of pollora:doctor and Site Health; tagged on boot, so it comes after the framework's own
        $this->app->tag([AsyncActionsCheck::class], DoctorServiceProvider::CHECKS_TAG);

        $this->configureAsync($this->app->make(Repository::class));
    }

    private function configureAsync(Repository $config): void
    {
        $container = $this->app;

        Async::extend('queue', static fn (): AsyncDriver => new QueueDriver($container->make(Dispatcher::class), $config));
        Async::setAutoDrivers(self::autoDrivers($config));

        $default = $config->get('hooks.async.default', 'auto');
        Async::setDefaultDriver(is_string($default) && $default !== '' && $default !== 'auto' ? $default : null);

        Async::setDefaults(
            tries: (int) $config->get('hooks.async.tries', 1),
            backoff: (array) $config->get('hooks.async.backoff', [10, 60, 300]),
            asUser: (bool) $config->get('hooks.async.as_user', false),
        );

        Async::setDebug((bool) $config->get('app.debug', false));
        Async::useClosureKey(is_string($key = $config->get('app.key')) ? $key : null);
        Async::reference(new EloquentModelReference);
        Async::injectParametersUsing(static fn (\ReflectionParameter $parameter): mixed => self::resolveParameter($container, $parameter));

        Async::reportUsing(static function (string $message, array $context) use ($container): void {
            $container->make(LoggerInterface::class)->log(isset($context['exception']) ? 'error' : 'warning', $message, $context);
        });
    }

    /**
     * The drivers auto tries, in order. The Laravel queue needs a worker: it comes first only once a connection is set for async actions.
     *
     * @return list<string>
     */
    public static function autoDrivers(Repository $config): array
    {
        return $config->get('hooks.async.queue.connection') !== null
            ? ['queue', 'action-scheduler', 'wp-cron']
            : ['action-scheduler', 'wp-cron'];
    }

    private static function resolveParameter(Container $container, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType ? $container->make($type->getName()) : null;
    }
}
