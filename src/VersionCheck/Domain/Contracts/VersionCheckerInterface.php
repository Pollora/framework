<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Domain\Contracts;

use Pollora\VersionCheck\Infrastructure\Services\PackagistVersionChecker;

/**
 * Contract for checking Pollora framework version information.
 *
 * Implementations are responsible for determining the currently installed
 * version and fetching the latest available version from a remote source.
 *
 * @see PackagistVersionChecker
 */
interface VersionCheckerInterface
{
    /**
     * Get the latest stable version available from the remote source.
     *
     * @return string|null The latest version string (e.g. "13.3.0"), or null if unavailable
     */
    public function getLatestVersion(): ?string;

    /**
     * Get the currently installed version of the Pollora framework.
     *
     * @return string|null The current version string (e.g. "13.3.0"), or null if undetermined
     */
    public function getCurrentVersion(): ?string;
}
