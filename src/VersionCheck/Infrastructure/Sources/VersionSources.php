<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

use Pollora\VersionCheck\Domain\Contracts\VersionSource;

/**
 * Where a package's releases are read, from the repositories of the project's
 * composer.json, in the order Composer reads them:
 *
 * - each `composer` repository (Private Packagist, Satis) that may serve the
 *   package, given its `only` and `exclude`;
 * - each `vcs` repository on GitHub named after the package;
 * - Packagist last, unless the project turned it off (`"packagist.org": false`).
 *
 * The first one that knows the package answers: a repository that does not
 * serve it (401, 403, 404) hands over to the next, as in Composer.
 */
class VersionSources
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $repositories = null;

    private bool $packagistDisabled = false;

    public function __construct(
        private readonly HttpGet $http,
        private readonly string $composerJsonPath,
        private readonly ?string $gitHubToken = null,
    ) {}

    public function for(string $package): VersionSource
    {
        $sources = [];
        $packagist = false;

        foreach ($this->repositories() as $repository) {
            $type = $repository['type'] ?? null;
            $url = is_string($repository['url'] ?? null) ? $repository['url'] : '';

            if ($type === 'composer' && $url !== '' && $this->serves($repository, $package)) {
                // Packagist listed by hand keeps its place in the order
                $packagist = $packagist || ComposerRepositorySource::isPackagist($url);
                $sources[] = new ComposerRepositorySource($this->http, $url);

                continue;
            }

            if ($type === 'vcs' && preg_match('#github\.com[/:]([^/]+/[^/]+?)(\.git)?/?$#', $url, $matches) === 1
                && strtolower(basename($matches[1])) === strtolower(basename($package))) {
                $sources[] = new GitHubSource($this->http, $matches[1], $this->gitHubToken);
            }
        }

        if (! $packagist && ! $this->packagistDisabled) {
            $sources[] = new ComposerRepositorySource($this->http);
        }

        return count($sources) === 1 ? $sources[0] : new FirstAnsweringSource($sources);
    }

    /**
     * Whether a repository may serve a package: listed by `only` when it has
     * one, and not listed by `exclude`.
     *
     * @param  array<string, mixed>  $repository
     */
    private function serves(array $repository, string $package): bool
    {
        if (isset($repository['only']) && ! $this->matches((array) $repository['only'], $package)) {
            return false;
        }

        return ! isset($repository['exclude']) || ! $this->matches((array) $repository['exclude'], $package);
    }

    /**
     * @param  array<array-key, mixed>  $patterns
     */
    private function matches(array $patterns, string $package): bool
    {
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && fnmatch($pattern, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The repositories, as a list or keyed by name; `"packagist.org": false`,
     * at the top level or as an entry of the list, turns Packagist off.
     *
     * @return array<int, array<string, mixed>>
     */
    private function repositories(): array
    {
        if ($this->repositories !== null) {
            return $this->repositories;
        }

        $composer = is_file($this->composerJsonPath) ? json_decode((string) file_get_contents($this->composerJsonPath), true) : null;
        $repositories = is_array($composer) ? (array) ($composer['repositories'] ?? []) : [];

        foreach ($repositories as $name => $repository) {
            if ((in_array($name, ['packagist.org', 'packagist'], true) && $repository === false)
                || (is_array($repository) && (($repository['packagist.org'] ?? null) === false || ($repository['packagist'] ?? null) === false))) {
                $this->packagistDisabled = true;
            }
        }

        return $this->repositories = array_values(array_filter($repositories, static fn (mixed $repository): bool => is_array($repository) && isset($repository['type'])));
    }
}
