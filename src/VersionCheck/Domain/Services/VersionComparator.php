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
     * Returns false if either version cannot be determined, or if the
     * installed version is a development build, ensuring no false-positive
     * update notifications are shown.
     */
    public function isUpdateAvailable(): bool
    {
        $current = $this->checker->getCurrentVersion();
        $latest = $this->checker->getLatestVersion();

        if ($current === null || $latest === null || $this->isDevelopmentBuild()) {
            return false;
        }

        return version_compare($latest, $current, '>');
    }

    /**
     * Determine whether the installed version is a development build.
     *
     * A branch install ("dev-develop", "13.x-dev") has no release number,
     * and version_compare() ranks it below every release, so it cannot be
     * compared with the latest stable version.
     */
    public function isDevelopmentBuild(): bool
    {
        $current = $this->checker->getCurrentVersion();

        if ($current === null) {
            return false;
        }

        return str_starts_with($current, 'dev-') || str_ends_with($current, '-dev');
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
