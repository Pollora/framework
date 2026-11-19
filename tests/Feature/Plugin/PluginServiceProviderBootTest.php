<?php

declare(strict_types=1);

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Modules\Infrastructure\Services\ModuleAutoloader;
use Pollora\Plugin\Infrastructure\Providers\PluginServiceProvider;
use Pollora\Plugin\Infrastructure\Services\PluginAutoloader;
use Pollora\Plugin\UI\Console\Commands\PluginActivateCommand;
use Pollora\Plugin\UI\Console\Commands\PluginListCommand;
use Pollora\Plugin\UI\Console\Commands\PluginStatusCommand;
use Pollora\Plugin\UI\Console\MakePluginCommand;

// The console bootstrappers are static: keep one test's commands out of the next.
beforeEach(fn () => ConsoleApplication::forgetBootstrappers());

afterEach(function (): void {
    ConsoleApplication::forgetBootstrappers();

    if ($this->app->bound(ModuleAutoloader::CLASS_LOADER)) {
        // boot() puts the plugin class loader on the autoload stack.
        $this->app->make(ModuleAutoloader::CLASS_LOADER)->unregister();
    }
});

it('merges the plugin configuration on boot', function (): void {
    $provider = new PluginServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect(config('plugin.path'))->toBe(base_path('plugins'))
        ->and(config('plugin.autoload.namespace_prefix'))->toBe('Plugin')
        ->and(config('plugin.autoload.preferred_source_dir'))->toBe('app')
        ->and(config('plugin.autoload.fallback_source_dir'))->toBe('src')
        ->and(config('plugin.discovery.required_headers'))->toBe(['Plugin Name', 'Version'])
        ->and(config('plugin.config.delayed_loading'))->toBe(['admin-menu.php', 'settings.php'])
        ->and(config('plugin.security.required_capabilities.activate'))->toBe('activate_plugins');
});

it('keeps what the application already configured over the defaults', function (): void {
    config(['plugin.path' => '/srv/custom-plugins']);

    $provider = new PluginServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect(config('plugin.path'))->toBe('/srv/custom-plugins')
        ->and(config('plugin.autoload.enabled'))->toBeTrue();
});

it('puts the plugin class loader on the autoload stack on boot', function (): void {
    $provider = new PluginServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $loader = $this->app->make(ModuleAutoloader::CLASS_LOADER);

    expect($this->app->make(PluginAutoloader::class))->toBeInstanceOf(PluginAutoloader::class)
        ->and(in_array([$loader, 'loadClass'], spl_autoload_functions(), true))->toBeTrue();
});

it('registers the plugin commands when running in the console', function (): void {
    // The commands resolve the plugin manager, which wants a real translator.
    $this->app->instance('translator', new Translator(new ArrayLoader, 'en'));
    $this->app->instance(ConsoleDetectionService::class, Mockery::mock(ConsoleDetectionService::class));
    (new PluginServiceProvider($this->app))->register();

    $console = new ConsoleApplication($this->app, $this->app['events'], $this->app->version());
    $commands = array_map(get_class(...), $console->all());

    expect($commands)->toContain(MakePluginCommand::class)
        ->toContain(PluginActivateCommand::class)
        ->toContain(PluginStatusCommand::class)
        ->toContain(PluginListCommand::class);
});
