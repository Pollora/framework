<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;
use Pollora\VersionCheck\UI\Http\SiteHealthCheck;

describe('SiteHealthCheck', function (): void {
    it('adds Pollora section to debug information', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $info = $health->addDebugInfo([]);

        expect($info)->toHaveKey('pollora');
        expect($info['pollora']['label'])->toBe('Pollora');
        expect($info['pollora']['fields']['version']['value'])->toBe('13.3.0');
        expect($info['pollora']['fields']['latest_version']['value'])->toBe('13.3.0');
        expect($info['pollora']['fields']['up_to_date']['value'])->toBe('Yes');
    });

    it('shows not up to date when update available', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $info = $health->addDebugInfo([]);

        expect($info['pollora']['fields']['up_to_date']['value'])->toBe('No');
    });

    it('adds version test to site status tests', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $tests = $health->addTests([]);

        expect($tests['direct'])->toHaveKey('pollora_update');
        expect($tests['direct']['pollora_update']['test'])->toBeCallable();
    });

    it('returns good status when up to date', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $result = $health->testVersionStatus();

        expect($result['status'])->toBe('good');
        expect($result['badge']['color'])->toBe('blue');
    });

    it('returns recommended status when update available', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $result = $health->testVersionStatus();

        expect($result['status'])->toBe('recommended');
        expect($result['badge']['color'])->toBe('orange');
        expect($result['label'])->toContain('13.3.0');
    });

    it('returns recommended status when version cannot be determined', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn(null);
        $checker->shouldReceive('getLatestVersion')->andReturn(null);

        $health = new SiteHealthCheck(new VersionComparator($checker));
        $result = $health->testVersionStatus();

        expect($result['status'])->toBe('recommended');
    });
});
