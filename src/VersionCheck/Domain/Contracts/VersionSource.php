<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Domain\Contracts;

/**
 * Where the latest release of a Composer package is read.
 */
interface VersionSource
{
    /**
     * The latest stable version of a package, without a leading "v", or null
     * when the source cannot tell (unreachable, unknown package, no release).
     */
    public function latest(string $package): ?string;

    /**
     * Where to read the release notes of a version, when the source knows.
     */
    public function releaseUrl(string $package, string $version): ?string;
}
