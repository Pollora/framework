<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;

describe('VersionComparator', function (): void {
    it('detects update available when latest is newer', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeTrue();
    });

    it('reports no update when versions match', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when current is newer', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('14.0.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when current version is null', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn(null);
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when latest version is null', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn(null);

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update for a development build', function (string $current): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn($current);
        $checker->shouldReceive('getLatestVersion')->andReturn('13.4.4');

        $comparator = new VersionComparator($checker);

        expect($comparator->isDevelopmentBuild())->toBeTrue();
        expect($comparator->isUpdateAvailable())->toBeFalse();
    })->with(['dev-develop', '13.x-dev']);

    it('reports no update when a pre-release is ahead of the latest stable', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.34.0-beta');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.4.4');

        $comparator = new VersionComparator($checker);

        expect($comparator->isDevelopmentBuild())->toBeFalse();
        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports an update when the stable release of a pre-release is out', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.34.0-beta');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.34.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeTrue();
    });

    it('delegates getCurrentVersion to checker', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->getCurrentVersion())->toBe('13.2.0');
    });

    it('delegates getLatestVersion to checker', function (): void {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->getLatestVersion())->toBe('13.3.0');
    });
});
