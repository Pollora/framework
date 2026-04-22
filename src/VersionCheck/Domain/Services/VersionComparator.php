<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Domain\Services;

use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;

/**
 * Compares the installed Pollora version against the latest available version.
 *
 * This domain service encapsulates the version comparison logic and acts as
 * the primary entry point for UI components (admin notice, Site Health) to
 * determine whether an update is available.
 *
 * @see VersionCheckerInterface
 */
class VersionComparator
{
    public function __construct(
        private readonly VersionCheckerInterface $checker
    ) {}

    /**
     * Determine whether a newer version of Pollora is available.
     *
     * Returns false if either version cannot be determined, ensuring
     * no false-positive update notifications are shown.
     */
    public function isUpdateAvailable(): bool
    {
        $current = $this->checker->getCurrentVersion();
        $latest = $this->checker->getLatestVersion();

        if ($current === null || $latest === null) {
            return false;
        }

        return version_compare($latest, $current, '>');
    }

    /**
     * Get the currently installed version.
     *
     * Delegates to the underlying VersionCheckerInterface implementation.
     *
     * @return string|null The current version string, or null if undetermined
     */
    public function getCurrentVersion(): ?string
    {
        return $this->checker->getCurrentVersion();
    }

    /**
     * Get the latest available version.
     *
     * Delegates to the underlying VersionCheckerInterface implementation.
     *
     * @return string|null The latest version string, or null if unavailable
     */
    public function getLatestVersion(): ?string
    {
        return $this->checker->getLatestVersion();
    }
}
