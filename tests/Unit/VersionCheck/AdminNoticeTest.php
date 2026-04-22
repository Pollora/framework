<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\UI\Http\AdminNotice;

require_once dirname(__DIR__).'/helpers.php';

beforeEach(function () {
    setupWordPressMocks();
});

describe('AdminNotice', function () {
    it('renders nothing when no update is available', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toBeEmpty();
    });

    it('renders a warning notice when update is available', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        setWordPressFunction('get_current_user_id', fn () => 1);
        setWordPressFunction('get_user_meta', fn () => '');
        setWordPressFunction('wp_create_nonce', fn () => 'test-nonce');

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

    it('renders nothing when notice is dismissed for current version', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        setWordPressFunction('get_current_user_id', fn () => 1);
        setWordPressFunction('get_user_meta', fn () => '13.3.0');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toBeEmpty();
    });
});
