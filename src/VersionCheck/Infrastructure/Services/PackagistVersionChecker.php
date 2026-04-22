<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Services;

use Composer\InstalledVersions;
use Pollora\VersionCheck\Domain\Contracts\VersionCheckerInterface;

/**
 * Checks Pollora version information using Packagist API and Composer runtime.
 *
 * The latest version is fetched from the Packagist API v2 endpoint and cached
 * in a WordPress transient for 12 hours to avoid excessive API calls.
 * The current version is read from Composer's installed packages metadata.
 *
 * Only stable versions are considered (dev, alpha, beta, and RC are skipped).
 */
class PackagistVersionChecker implements VersionCheckerInterface
{
    /** @var string Composer package name for the Pollora framework */
    private const string PACKAGE_NAME = 'pollora/framework';

    /** @var string Packagist API v2 endpoint for package metadata */
    private const string PACKAGIST_URL = 'https://repo.packagist.org/p2/pollora/framework.json';

    /** @var string WordPress transient key for caching the latest version */
    private const string TRANSIENT_KEY = 'pollora_latest_version';

    /** @var int Cache duration in seconds (12 hours) */
    private const int CACHE_TTL = 43200;

    /**
     * {@inheritDoc}
     *
     * Checks the WordPress transient cache first. On cache miss, fetches
     * from the Packagist API and stores the result for subsequent requests.
     */
    public function getLatestVersion(): ?string
    {
        $cached = get_transient(self::TRANSIENT_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $version = $this->fetchLatestVersion();

        if ($version !== null) {
            set_transient(self::TRANSIENT_KEY, $version, self::CACHE_TTL);
        }

        return $version;
    }

    /**
     * {@inheritDoc}
     *
     * Uses Composer's InstalledVersions runtime API to read the version
     * from the installed packages metadata. Strips the leading "v" prefix.
     */
    public function getCurrentVersion(): ?string
    {
        if (! InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);

        if ($version === null) {
            return null;
        }

        return ltrim($version, 'v');
    }

    /**
     * Fetch the latest version from the Packagist API.
     *
     * Uses WordPress HTTP API (wp_remote_get) with a 5-second timeout.
     * Returns null on any network or parsing error.
     */
    private function fetchLatestVersion(): ?string
    {
        $response = wp_remote_get(self::PACKAGIST_URL, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode((string) $body, true);

        if (! is_array($data)) {
            return null;
        }

        return $this->extractLatestStableVersion($data);
    }

    /**
     * Extract the latest stable version from Packagist API response data.
     *
     * Iterates through the package releases (ordered newest-first by Packagist)
     * and returns the first version that is not a dev, alpha, beta, or RC release.
     *
     * @param  array  $data  Decoded JSON response from Packagist API
     * @return string|null The latest stable version string, or null if none found
     */
    private function extractLatestStableVersion(array $data): ?string
    {
        $packages = $data['packages'][self::PACKAGE_NAME] ?? [];

        foreach ($packages as $release) {
            $version = $release['version'] ?? '';

            if (str_contains($version, 'dev')) {
                continue;
            }

            if (str_contains($version, 'alpha')) {
                continue;
            }

            if (str_contains($version, 'beta')) {
                continue;
            }

            if (str_contains($version, 'RC')) {
                continue;
            }

            return ltrim($version, 'v');
        }

        return null;
    }
}
