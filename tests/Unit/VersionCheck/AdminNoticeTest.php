<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\UI\Http\AdminNotice;

require_once dirname(__DIR__).'/helpers.php';

beforeEach(function (): void {
    setupWordPressMocks();
});

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

        setWordPressFunction('get_current_user_id', fn (): int => 1);
        setWordPressFunction('get_user_meta', fn (): string => '');
        setWordPressFunction('wp_create_nonce', fn (): string => 'test-nonce');

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

        setWordPressFunction('get_current_user_id', fn (): int => 1);
        setWordPressFunction('get_user_meta', fn (): string => '13.3.0');

        $notice = new AdminNotice(new VersionComparator($checker));

        ob_start();
        $notice->render();
        $output = ob_get_clean();

        expect($output)->toBeEmpty();
    });
});
