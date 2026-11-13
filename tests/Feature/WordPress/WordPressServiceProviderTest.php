<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\InstallationService;
use Pollora\Services\WordPress\Installation\LanguageService;
use Pollora\Services\WordPress\Installation\WordPressInstallLoaderService;
use Pollora\WordPress\Bootstrap;
use Pollora\WordPress\Commands\LaunchPadInstallCommand;
use Pollora\WordPress\Commands\LaunchPadSetupCommand;
use Pollora\WordPress\Config\ConstantManager;
use Pollora\WordPress\Config\ConstantServiceProvider;
use Pollora\WordPress\WordPressServiceProvider;

/**
 * @return array{WordPressServiceProvider, Mockery\MockInterface}
 */
function contentWordPressProvider(object $app, bool $console): array
{
    $detector = Mockery::mock(ConsoleDetectionService::class);
    $detector->shouldReceive('isConsole')->andReturn($console);

    $app->instance(ConsoleDetectionService::class, $detector);
    $app->instance(DebugDetectorInterface::class, Mockery::mock(DebugDetectorInterface::class));

    if (! $app->bound(Action::class) || ! $app->make(Action::class) instanceof Mockery\MockInterface) {
        $app->instance(Action::class, Mockery::mock(Action::class));
    }

    $app->instance(Filter::class, Mockery::mock(Filter::class));

    $bootstrap = Mockery::mock(Bootstrap::class);
    // The real Bootstrap would define constants for the rest of the process.
    $app->extend(Bootstrap::class, fn () => $bootstrap);

    return [new WordPressServiceProvider($app, $detector), $bootstrap];
}

beforeEach(function (): void {
    $this->server = $_SERVER;
});

afterEach(function (): void {
    $_SERVER = $this->server;
    URL::forceScheme(null);
});

describe('ConstantServiceProvider', function (): void {
    it('shares one constant manager', function (): void {
        $this->app->register(ConstantServiceProvider::class);

        expect($this->app->make('constant.manager'))->toBeInstanceOf(ConstantManager::class)
            ->and($this->app->make('constant.manager'))->toBe($this->app->make('constant.manager'));
    });
});

describe('WordPressServiceProvider', function (): void {
    it('registers the bootstrap once and shares the installation services', function (): void {
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: false);
        $bootstrap->shouldReceive('register')->once();

        $provider->register();

        expect($this->app->make(Bootstrap::class))->toBe($this->app->make(Bootstrap::class));

        foreach ([DatabaseService::class, InstallationService::class, LanguageService::class, WordPressInstallLoaderService::class] as $service) {
            expect($this->app->make($service))->toBe($this->app->make($service));
        }
    });

    it('merges the default WordPress configuration under the application own', function (): void {
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: false);
        $bootstrap->shouldReceive('register');

        $provider->register();

        expect(config('wordpress'))->toBeArray()->not->toBeEmpty();
    });

    it('boots WordPress and offers its commands in the console', function (): void {
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: true);
        $bootstrap->shouldReceive('register')->once();
        $bootstrap->shouldReceive('boot')->once();
        $provider->register();

        $provider->boot();

        $commands = array_map(fn ($command): string => $command::class, Artisan::all());

        expect($commands)->toContain(LaunchPadSetupCommand::class)->toContain(LaunchPadInstallCommand::class);
    });

    it('finishes the web installation on wp_install when the request is not a console one', function (): void {
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: false);
        $bootstrap->shouldReceive('register');
        $bootstrap->shouldReceive('boot')->once();

        $hooks = [];
        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->andReturnUsing(function (string $hook, callable $callback) use (&$hooks, $action) {
            $hooks[$hook] = $callback;

            return $action;
        });
        $this->app->instance(Action::class, $action);
        $provider->register();

        $provider->boot();

        expect(array_keys($hooks))->toBe(['wp_install'])
            ->and($hooks['wp_install'])->toBeCallable();
    });

    it('forces https when the application URL is secure', function (): void {
        config(['app.url' => 'https://secure.example.test']);
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: true);
        $bootstrap->shouldReceive('register');
        $bootstrap->shouldReceive('boot');
        $provider->register();

        $provider->boot();

        expect($_SERVER['HTTPS'])->toBe('on')
            ->and(url('/page'))->toStartWith('https://');
    });

    it('leaves the scheme alone when the application URL is not secure', function (): void {
        unset($_SERVER['HTTPS']);
        config(['app.url' => 'http://plain.example.test']);
        [$provider, $bootstrap] = contentWordPressProvider($this->app, console: true);
        $bootstrap->shouldReceive('register');
        $bootstrap->shouldReceive('boot');
        $provider->register();

        $provider->boot();

        expect($_SERVER)->not->toHaveKey('HTTPS');
    });
});
