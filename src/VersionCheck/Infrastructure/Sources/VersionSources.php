<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

use Pollora\VersionCheck\Domain\Contracts\VersionSource;

/**
 * Pick where a package's releases are read, from the repositories of the
 * project's composer.json:
 *
 * - a `composer` repository (Private Packagist, Satis) whose `only` lists the
 *   package, or that has no `only` and is not Packagist;
 * - a `vcs` repository on GitHub named after the package;
 * - Packagist otherwise.
 */
class VersionSources
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $repositories = null;

    public function __construct(
        private readonly HttpGet $http,
        private readonly string $composerJsonPath,
        private readonly ?string $gitHubToken = null,
    ) {}

    public function for(string $package): VersionSource
    {
        foreach ($this->repositories() as $repository) {
            $type = $repository['type'] ?? null;
            $url = is_string($repository['url'] ?? null) ? $repository['url'] : '';

            if ($type === 'composer' && $url !== '' && ! str_contains($url, 'packagist.org') && $this->serves($repository, $package)) {
                return new ComposerRepositorySource($this->http, $url);
            }

            if ($type === 'vcs' && preg_match('#github\.com[/:]([^/]+/[^/]+?)(\.git)?/?$#', $url, $matches) === 1
                && strtolower(basename($matches[1])) === strtolower(basename($package))) {
                return new GitHubSource($this->http, $matches[1], $this->gitHubToken);
            }
        }

        return new ComposerRepositorySource($this->http);
    }

    /**
     * @param  array<string, mixed>  $repository
     */
    private function serves(array $repository, string $package): bool
    {
        if (! isset($repository['only'])) {
            return true;
        }

        foreach ((array) $repository['only'] as $pattern) {
            if (is_string($pattern) && fnmatch($pattern, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function repositories(): array
    {
        if ($this->repositories !== null) {
            return $this->repositories;
        }

        $composer = is_file($this->composerJsonPath) ? json_decode((string) file_get_contents($this->composerJsonPath), true) : null;
        $repositories = is_array($composer) ? ($composer['repositories'] ?? []) : [];

        return $this->repositories = array_values(array_filter((array) $repositories, is_array(...)));
    }
}
