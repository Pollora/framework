<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Dashboard\Infrastructure\Providers\DashboardServiceProvider;
use Pollora\Dashboard\UI\Http\DashboardController;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;

/**
 * Boot the provider as WordPress admin would, then fire the admin_menu callback.
 *
 * @return array<string, mixed> The arguments given to add_submenu_page().
 */
function fireDashboardMenu(object $app, ?VersionComparator $comparator): array
{
    Functions\when('is_admin')->justReturn(true);

    if ($comparator instanceof VersionComparator) {
        $app->instance(VersionComparator::class, $comparator);
    }

    $controller = (new ReflectionClass(DashboardController::class))->newInstanceWithoutConstructor();
    $app->instance(DashboardController::class, $controller);

    $callback = null;
    $action = Mockery::mock(Action::class);
    $action->shouldReceive('add')->with('admin_menu', Mockery::type('Closure'))
        ->andReturnUsing(function (string $hook, Closure $closure) use (&$callback, $action): Action {
            $callback = $closure;

            return $action;
        });

    (new DashboardServiceProvider($app))->boot($action);

    $captured = [];
    Functions\when('add_submenu_page')->alias(function (...$args) use (&$captured): void {
        $captured = $args;
    });

    $callback();

    return $captured;
}

describe('Dashboard admin menu', function (): void {
    it('adds Pollora under Tools, guarded by manage_options, with the dashboard controller', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class, [
            'getCurrentVersion' => '13.40.0',
            'getLatestVersion' => '13.40.0',
        ]);

        $args = fireDashboardMenu($this->app, new VersionComparator($checker));

        expect($args[0])->toBe('tools.php')
            ->and($args[1])->toBe('Pollora')
            ->and($args[2])->toBe('Pollora')
            ->and($args[3])->toBe('manage_options')
            ->and($args[4])->toBe('pollora-dashboard')
            ->and($args[5])->toBeInstanceOf(DashboardController::class);
    });

    it('shows a notification badge on the menu when an update is available', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class, [
            'getCurrentVersion' => '13.39.0',
            'getLatestVersion' => '13.40.0',
        ]);

        $args = fireDashboardMenu($this->app, new VersionComparator($checker));

        expect($args[2])->toBe('Pollora <span class="update-plugins count-1"><span class="plugin-count">1</span></span>');
    });

    it('shows no badge when the version check fails', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andThrow(new RuntimeException('offline'));
        $checker->shouldReceive('getLatestVersion')->andThrow(new RuntimeException('offline'));

        $args = fireDashboardMenu($this->app, new VersionComparator($checker));

        expect($args[2])->toBe('Pollora');
    });
});
