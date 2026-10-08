<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

use Pollora\VersionCheck\Domain\Contracts\VersionSource;
use Pollora\VersionCheck\Domain\Services\StableVersions;

/**
 * A package from a GitHub repository (a Composer `vcs` repository): its latest
 * release, else its highest tag. A token reads a private repository.
 */
class GitHubSource implements VersionSource
{
    public function __construct(
        private readonly HttpGet $http,
        private readonly string $repository,
        private readonly ?string $token = null,
    ) {}

    public function latest(string $package): ?string
    {
        $headers = ['Accept' => 'application/vnd.github+json'];

        if ($this->token !== null && $this->token !== '') {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        $release = $this->http->json(sprintf('https://api.github.com/repos/%s/releases/latest', $this->repository), $headers);

        if (is_array($release) && is_string($release['tag_name'] ?? null)) {
            $version = ltrim($release['tag_name'], 'vV');

            if (StableVersions::isStable($version)) {
                return $version;
            }
        }

        $tags = $this->http->json(sprintf('https://api.github.com/repos/%s/tags?per_page=100', $this->repository), $headers);

        return is_array($tags)
            ? StableVersions::highest(array_map(fn (mixed $tag): mixed => is_array($tag) ? ($tag['name'] ?? null) : null, $tags))
            : null;
    }

    public function releaseUrl(string $package, string $version): ?string
    {
        return sprintf('https://github.com/%s/releases', $this->repository);
    }
}
