<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Pollora\Ajax\Application\Service\RegisterAjaxActionService;
use Pollora\Ajax\Domain\Model\AjaxAction;
use Pollora\Ajax\Domain\Model\NoncePolicy;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Port\Out\AjaxActionRegistrarPort;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Application\Domain\Contracts\DebugDetectorInterface;
use Pollora\Discovery\Infrastructure\Providers\DiscoveryServiceProvider;
use Pollora\Hook\Adapter\Out\WordPress\Action;
use Pollora\Hook\Adapter\Out\WordPress\Filter;
use Pollora\Hook\Domain\Contract\Action as ActionContract;
use Pollora\Hook\Domain\Contract\Filter as FilterContract;
use Pollora\Hook\Infrastructure\Providers\HookServiceProvider;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\Infrastructure\Providers\VersionCheckServiceProvider;
use Pollora\VersionCheck\Infrastructure\Services\PackagistVersionChecker;
use Pollora\VersionCheck\UI\Http\AdminNotice;

beforeEach(function (): void {
    // Setup shared dependencies
    $consoleDetection = Mockery::mock(ConsoleDetectionService::class);
    $consoleDetection->shouldReceive('isConsole')->andReturn(false);

    $this->app->instance(ConsoleDetectionService::class, $consoleDetection);
    $this->app->singleton(DebugDetectorInterface::class, fn () => Mockery::mock(DebugDetectorInterface::class, [
        'isDebugMode' => false,
    ]));
    // The AJAX actions the provider declares are collected here instead of being hooked into WordPress
    $declared = new ArrayObject;
    $this->declared = $declared;
    $this->app->singleton('wp.ajax', fn (): AjaxFactory => new AjaxFactory(new RegisterAjaxActionService(
        new readonly class($declared) implements AjaxActionRegistrarPort
        {
            public function __construct(private ArrayObject $declared) {}

            public function register(AjaxAction $action): void
            {
                $this->declared->append($action);
            }
        }
    )));

    // Register dependencies and provider
    $this->app->register(DiscoveryServiceProvider::class);
    $this->app->register(new HookServiceProvider($this->app, $consoleDetection));
    $this->app->register(VersionCheckServiceProvider::class);
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
});

describe('VersionCheckServiceProvider', function (): void {
    it('binds VersionCheckerInterface to PackagistVersionChecker', function (): void {
        $checker = $this->app->make(VersionCheckerInterface::class);

        expect($checker)->toBeInstanceOf(PackagistVersionChecker::class);
    });

    it('returns same VersionChecker instance (singleton)', function (): void {
        $checker1 = $this->app->make(VersionCheckerInterface::class);
        $checker2 = $this->app->make(VersionCheckerInterface::class);

        expect($checker1)->toBe($checker2);
    });

    it('binds VersionComparator as singleton', function (): void {
        $comparator = $this->app->make(VersionComparator::class);

        expect($comparator)->toBeInstanceOf(VersionComparator::class);
        expect($this->app->make(VersionComparator::class))->toBe($comparator);
    });

    it('does not register admin hooks when not in admin', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(false);

        $action = Mockery::mock(Action::class);
        $action->shouldNotReceive('add')->with('admin_notices', Mockery::any());
        $action->shouldNotReceive('add')->with('wp_ajax_pollora_dismiss_update_notice', Mockery::any());

        $filter = Mockery::mock(Filter::class);
        $filter->shouldNotReceive('add')->with('debug_information', Mockery::any());
        $filter->shouldNotReceive('add')->with('site_status_tests', Mockery::any());

        $provider = new VersionCheckServiceProvider($this->app);
        $provider->boot($action, $filter);

        expect($this->declared)->toBeEmpty(); // no AJAX action either; Mockery assertions verified in tearDown
    });

    it('registers admin hooks when in admin context', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(true);

        $action = Mockery::mock(Action::class);
        $action->shouldReceive('add')->with('admin_notices', Mockery::type('array'))->once();
        // The dismissal is declared through Ajax::listen(): a hand-written wp_ajax_ hook would run unchecked
        $action->shouldNotReceive('add')->with('wp_ajax_pollora_dismiss_update_notice', Mockery::any());
        $this->app->instance(ActionContract::class, $action);

        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('add')->with('debug_information', Mockery::type('array'))->once();
        $filter->shouldReceive('add')->with('site_status_tests', Mockery::type('array'))->once();
        $this->app->instance(FilterContract::class, $filter);

        $provider = new VersionCheckServiceProvider($this->app);
        $provider->boot($action, $filter);

        expect(true)->toBeTrue(); // Mockery assertions verified in tearDown
    });
});

describe('VersionCheckServiceProvider AJAX dismissal', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(true);

        $this->bootProvider = function (): void {
            (new VersionCheckServiceProvider($this->app))->boot(
                Mockery::mock(Action::class)->shouldIgnoreMissing(),
                Mockery::mock(Filter::class)->shouldIgnoreMissing()
            );
        };
    });

    it('declares the dismissal through Ajax::listen(), for signed-in users, with its nonce and capability required', function (): void {
        ($this->bootProvider)();

        expect($this->declared)->toHaveCount(1);

        /** @var AjaxAction $declared */
        $declared = $this->declared[0];

        expect($declared->getName())->toBe('pollora_dismiss_update_notice')
            ->and($declared->getCallback())->toBeArray()
            ->and($declared->getCallback()[0])->toBeInstanceOf(AdminNotice::class)
            ->and($declared->getCallback()[1])->toBe('dismiss')
            ->and($declared->isBothOrLoggedUsers())->toBeTrue()
            ->and($declared->isBothOrGuestUsers())->toBeFalse()
            ->and($declared->getNoncePolicy())->toBe(NoncePolicy::Verify)
            ->and($declared->getNonceAction())->toBe(AdminNotice::NONCE_ACTION)
            ->and($declared->getCapability())->toBe(AdminNotice::CAPABILITY);
    });

    it('declares no AJAX action outside the admin', function (): void {
        Brain\Monkey\Functions\when('is_admin')->justReturn(false);

        ($this->bootProvider)();

        expect($this->declared)->toBeEmpty();
    });
});
