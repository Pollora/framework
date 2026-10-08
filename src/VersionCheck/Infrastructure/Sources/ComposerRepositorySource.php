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
        return str_contains($this->url, 'packagist.org') ? sprintf('https://packagist.org/packages/%s#%s', $package, $version) : null;
    }
}
