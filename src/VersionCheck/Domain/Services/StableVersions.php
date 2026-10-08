<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Domain\Services;

/**
 * Pick the highest stable version among release names.
 */
final class StableVersions
{
    /**
     * @param  iterable<mixed>  $versions
     */
    public static function highest(iterable $versions): ?string
    {
        $highest = null;

        foreach ($versions as $version) {
            if (! is_string($version)) {
                continue;
            }

            $version = ltrim(trim($version), 'vV');

            if (! self::isStable($version)) {
                continue;
            }

            if ($highest === null || version_compare($version, $highest, '>')) {
                $highest = $version;
            }
        }

        return $highest;
    }

    public static function isStable(string $version): bool
    {
        return preg_match('/^\d+(\.\d+)*$/', $version) === 1;
    }

    /**
     * Whether an installed version is a development build, never compared with releases.
     */
    public static function isDevelopmentBuild(string $version): bool
    {
        return str_starts_with($version, 'dev-') || str_ends_with($version, '-dev');
    }
}
