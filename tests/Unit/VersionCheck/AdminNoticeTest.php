<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\UI\Http\AdminNotice;

describe('AdminNotice', function (): void {
    it('renders nothing when no update is available', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toBeEmpty();
    });

    it('renders a warning notice when update is available', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        Brain\Monkey\Functions\when('get_current_user_id')->justReturn(1);
        Brain\Monkey\Functions\when('get_user_meta')->justReturn('');
        Brain\Monkey\Functions\when('wp_create_nonce')->justReturn('test-nonce');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toContain('Pollora 13.3.0');
        expect($output)->toContain('13.2.0');
        expect($output)->toContain('notice-warning');
        expect($output)->toContain('is-dismissible');
        expect($output)->toContain('changelog');
    });

    it('renders nothing when notice is dismissed for current version', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        Brain\Monkey\Functions\when('get_current_user_id')->justReturn(1);
        Brain\Monkey\Functions\when('get_user_meta')->justReturn('13.3.0');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toBeEmpty();
    });
});

describe('AdminNotice dismissal', function (): void {
    beforeEach(function (): void {
        $this->notice = new AdminNotice(new VersionComparator(Mockery::mock(VersionCheckerInterface::class)));
        Brain\Monkey\Functions\when('check_ajax_referer')->justReturn(true);
        Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();
        Brain\Monkey\Functions\when('get_current_user_id')->justReturn(5);
        Brain\Monkey\Functions\when('wp_die')->justReturn(null);
    });

    afterEach(function (): void {
        Facade::clearResolvedInstances();
    });

    it('stores the dismissed version for the current user', function (): void {
        $stored = [];
        Brain\Monkey\Functions\when('update_user_meta')->alias(function (int $user, string $key, string $value) use (&$stored): bool {
            $stored[] = [$user, $key, $value];

            return true;
        });
        Illuminate\Support\Facades\Request::swap(new Request([], ['version' => '13.40.1'], [], [], [], ['REQUEST_METHOD' => 'POST']));

        $this->notice->dismiss();

        expect($stored)->toBe([[5, 'pollora_dismissed_update_version', '13.40.1']]);
    });

    it('stores nothing when no version is sent', function (): void {
        $stored = [];
        Brain\Monkey\Functions\when('update_user_meta')->alias(function () use (&$stored): bool {
            $stored[] = true;

            return true;
        });
        Illuminate\Support\Facades\Request::swap(new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']));

        $this->notice->dismiss();

        expect($stored)->toBe([]);
    });
});
