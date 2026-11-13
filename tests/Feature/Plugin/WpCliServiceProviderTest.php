<?php

declare(strict_types=1);

use Illuminate\Console\Application as ConsoleApplication;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\WpCli\Application\Services\WpCliService;
use Pollora\WpCli\Infrastructure\Adapters\WpCliAdapter;
use Pollora\WpCli\Infrastructure\Providers\WpCliServiceProvider;
use Pollora\WpCli\Infrastructure\Services\WpCliDiscovery;
use Pollora\WpCli\UI\Console\WpCliMakeCommand;

// The console bootstrappers are static: keep one test's commands out of the next.
beforeEach(fn () => ConsoleApplication::forgetBootstrappers());
afterEach(fn () => ConsoleApplication::forgetBootstrappers());

function wpCliProvider(object $app, bool $console): WpCliServiceProvider
{
    $detection = Mockery::mock(ConsoleDetectionService::class);
    $detection->shouldReceive('isConsole')->andReturn($console);

    return new WpCliServiceProvider($app, $detection);
}

it('builds the service on the shared adapter and the discovery on the shared service', function (): void {
    wpCliProvider($this->app, console: false)->register();

    expect($this->app->make(WpCliAdapter::class))->toBeInstanceOf(WpCliAdapter::class)
        ->and($this->app->make(WpCliAdapter::class))->toBe($this->app->make(WpCliAdapter::class))
        ->and($this->app->make(WpCliService::class))->toBe($this->app->make(WpCliService::class))
        ->and($this->app->make(WpCliDiscovery::class))->toBe($this->app->make(WpCliDiscovery::class));
});

it('registers the make command when running in the console', function (): void {
    wpCliProvider($this->app, console: true)->register();

    $console = new ConsoleApplication($this->app, $this->app['events'], $this->app->version());

    expect($console->all())->toHaveKey('pollora:make:wp-cli')
        ->and($console->get('pollora:make:wp-cli'))->toBeInstanceOf(WpCliMakeCommand::class);
});

it('leaves the make command out of web requests', function (): void {
    wpCliProvider($this->app, console: false)->register();

    $console = new ConsoleApplication($this->app, $this->app['events'], $this->app->version());

    expect($console->all())->not->toHaveKey('pollora:make:wp-cli');
});

it('does not touch the WP CLI service on boot outside WP CLI', function (): void {
    $provider = wpCliProvider($this->app, console: false);
    $provider->register();
    $provider->boot();

    expect($this->app->resolved(WpCliService::class))->toBeFalse();
});
