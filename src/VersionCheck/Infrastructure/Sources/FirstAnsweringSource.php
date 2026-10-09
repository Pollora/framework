<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

use Pollora\VersionCheck\Domain\Contracts\VersionSource;

/**
 * Several places a package may come from, asked in Composer's order: the
 * first one that knows the package answers.
 *
 * A private repository that does not serve the package answers 401, 403 or
 * 404, which reads as "no answer", and the next one is asked, as Composer
 * moves on to the next repository.
 */
class FirstAnsweringSource implements VersionSource
{
    /**
     * @param  list<VersionSource>  $sources  In the order Composer reads them
     */
    public function __construct(
        private readonly array $sources,
    ) {}

    public function latest(string $package): ?string
    {
        foreach ($this->sources as $source) {
            $latest = $source->latest($package);

            if ($latest !== null) {
                return $latest;
            }
        }

        return null;
    }

    /**
     * The first release notes a source can point to, without asking any of
     * them again: only Packagist and GitHub have such a page, a private
     * repository never does.
     */
    public function releaseUrl(string $package, string $version): ?string
    {
        foreach ($this->sources as $source) {
            $url = $source->releaseUrl($package, $version);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return list<VersionSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }
}
