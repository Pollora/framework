<?php

declare(strict_types=1);

use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Dashboard\Domain\Services\SystemInfoCollector;
use Pollora\Dashboard\Infrastructure\Providers\DashboardServiceProvider;
use Pollora\Discovery\Infrastructure\Providers\DiscoveryServiceProvider;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Infrastructure\Providers\HookServiceProvider;
use Pollora\VersionCheck\Infrastructure\Providers\VersionCheckServiceProvider;

beforeEach(function (): void {
    $consoleDetection = Mockery::mock(ConsoleDetectionService::class);
    $consoleDetection->shouldReceive('isConsole')->andReturn(false);

    $this->app->instance(ConsoleDetectionService::class, $consoleDetection);
    $this->app->singleton(DebugDetectorInterface::class, fn () => Mockery::mock(DebugDetectorInterface::class, [
        'isDebugMode' => false,
    ]));

    $this->app->register(DiscoveryServiceProvider::class);
    $this->app->register(new HookServiceProvider($this->app, $consoleDetection));
    $this->app->register(VersionCheckServiceProvider::class);
    $this->app->register(DashboardServiceProvider::class);
});

describe('DashboardServiceProvider', function (): void {
    it('binds SystemInfoCollector as singleton', function (): void {
        $collector = $this->app->make(SystemInfoCollector::class);

        expect($collector)->toBeInstanceOf(SystemInfoCollector::class);
        expect($this->app->make(SystemInfoCollector::class))->toBe($collector);
    });

    it('does not register admin menu when not in admin', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(false);

        $action = Mockery::mock(Action::class);
        $action->shouldNotReceive('add');

        $this->app->instance(ActionContract::class, $action);

        $provider = new DashboardServiceProvider($this->app);
        $provider->boot($action);

        expect(true)->toBeTrue();
    });

    it('registers admin_menu hook when in admin context', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(true);

        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->with('admin_menu', Mockery::type('Closure'))->once();
        $this->app->instance(ActionContract::class, $action);

        $provider = new DashboardServiceProvider($this->app);
        $provider->boot($action);

        expect(true)->toBeTrue();
    });
});
