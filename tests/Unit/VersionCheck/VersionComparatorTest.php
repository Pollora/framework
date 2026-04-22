<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;
use Pollora\VersionCheck\Domain\Services\VersionComparator;

describe('VersionComparator', function () {
    it('detects update available when latest is newer', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeTrue();
    });

    it('reports no update when versions match', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when current is newer', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('14.0.0');
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when current version is null', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn(null);
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('reports no update when latest version is null', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.3.0');
        $checker->shouldReceive('getLatestVersion')->andReturn(null);

        $comparator = new VersionComparator($checker);

        expect($comparator->isUpdateAvailable())->toBeFalse();
    });

    it('delegates getCurrentVersion to checker', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getCurrentVersion')->andReturn('13.2.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->getCurrentVersion())->toBe('13.2.0');
    });

    it('delegates getLatestVersion to checker', function () {
        $checker = Mockery::mock(VersionCheckerInterface::class);
        $checker->shouldReceive('getLatestVersion')->andReturn('13.3.0');

        $comparator = new VersionComparator($checker);

        expect($comparator->getLatestVersion())->toBe('13.3.0');
    });
});
