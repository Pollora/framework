<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Application\Services;

use Composer\InstalledVersions;
use Pollora\VersionCheck\Domain\Services\StableVersions;
use Pollora\VersionCheck\Infrastructure\Sources\VersionSources;

/**
 * Whether an installed Composer package is up to date.
 *
 * The latest version is kept in a transient per package (12 hours, 1 hour
 * when the source did not answer, so an unreachable source does not slow every
 * admin page). Reading never fetches: refresh() does, from a scheduled task or
 * pollora:module:outdated — never during a front-end request.
 */
class PackageVersionChecker
{
    public const int TTL = 43200;

    public const int NEGATIVE_TTL = 3600;

    public function __construct(private readonly VersionSources $sources) {}

    public function current(string $package): ?string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion($package);

        return $version === null ? null : ltrim($version, 'vV');
    }

    /**
     * The latest version from the cache, without fetching.
     */
    public function cachedLatest(string $package): ?string
    {
        $cached = function_exists('get_transient') ? get_transient($this->key($package)) : false;

        return is_array($cached) && is_string($cached['latest'] ?? null) ? $cached['latest'] : null;
    }

    /**
     * Fetch the latest version and cache the answer, an empty one included.
     */
    public function refresh(string $package): ?string
    {
        $latest = $this->sources->for($package)->latest($package);

        if (function_exists('set_transient')) {
            set_transient($this->key($package), ['latest' => $latest], $latest === null ? self::NEGATIVE_TTL : self::TTL);
        }

        return $latest;
    }

    public function releaseUrl(string $package, string $version): ?string
    {
        return $this->sources->for($package)->releaseUrl($package, $version);
    }

    public function isUpdateAvailable(?string $current, ?string $latest): bool
    {
        return $current !== null && $latest !== null
            && ! StableVersions::isDevelopmentBuild($current)
            && version_compare($latest, $current, '>');
    }

    private function key(string $package): string
    {
        return 'pollora_latest_'.sha1($package);
    }
}
