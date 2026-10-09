<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

use Pollora\VersionCheck\Domain\Contracts\VersionSource;
use Pollora\VersionCheck\Domain\Services\StableVersions;

/**
 * A Composer repository answering {url}/p2/{package}.json: Packagist,
 * Private Packagist, Satis.
 */
class ComposerRepositorySource implements VersionSource
{
    public function __construct(
        private readonly HttpGet $http,
        private readonly string $url = 'https://repo.packagist.org',
    ) {}

    public function latest(string $package): ?string
    {
        $data = $this->http->json(rtrim($this->url, '/').'/p2/'.$package.'.json');

        $releases = $data['packages'][$package] ?? null;

        if (! is_array($releases)) {
            return null;
        }

        return StableVersions::highest(array_map(
            fn (mixed $release): mixed => is_array($release) ? ($release['version'] ?? null) : null,
            $releases,
        ));
    }

    public function releaseUrl(string $package, string $version): ?string
    {
        return self::isPackagist($this->url) ? sprintf('https://packagist.org/packages/%s#%s', $package, $version) : null;
    }

    /**
     * Whether a repository URL is Packagist itself, by its host: wpackagist.org
     * contains "packagist.org" but is another repository.
     */
    public static function isPackagist(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'packagist.org' || str_ends_with($host, '.packagist.org');
    }
}
