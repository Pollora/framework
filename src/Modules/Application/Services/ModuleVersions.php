<?php

declare(strict_types=1);

namespace Pollora\Modules\Application\Services;

use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Pollora\VersionCheck\Application\Services\PackageVersionChecker;
use Pollora\VersionCheck\Domain\Services\StableVersions;

/**
 * Versions of the modules installed by Composer.
 *
 * A module installed as a package (type laravel-module, placed in Modules/ by
 * an installer or scanned in vendor/) is matched to it by install path, and
 * carries that package's version. A local module — its composer.json merged
 * into the project's — has no version: nothing is shown, checked or requested.
 */
class ModuleVersions
{
    /**
     * @var array<string, string>|null Package name by real install path
     */
    private ?array $packagesByPath = null;

    public function __construct(
        private readonly Container $app,
        private readonly PackageVersionChecker $checker,
    ) {}

    /**
     * The Composer package a module was installed from, or null for a local module.
     */
    public function packageOf(string $modulePath): ?string
    {
        $path = realpath($modulePath);

        return $path === false ? null : ($this->packagesByPath()[$path] ?? null);
    }

    /**
     * Version of each module installed by Composer, by module name, read from the cache.
     *
     * @return array<string, array{package: string, version: string, latest: string|null, development: bool, update: bool, release_url: string|null}>
     */
    public function all(): array
    {
        $versions = [];

        foreach ($this->composerModules() as $name => $package) {
            $versions[$name] = $this->describe($package, $this->checker->cachedLatest($package));
        }

        return $versions;
    }

    /**
     * Fetch the latest version of every module installed by Composer.
     *
     * @return array<string, array{package: string, version: string, latest: string|null, development: bool, update: bool, release_url: string|null}>
     */
    public function refresh(): array
    {
        $versions = [];

        foreach ($this->composerModules() as $name => $package) {
            $versions[$name] = $this->describe($package, $this->checker->refresh($package));
        }

        return $versions;
    }

    /**
     * @return array{package: string, version: string, latest: string|null, development: bool, update: bool, release_url: string|null}
     */
    private function describe(string $package, ?string $latest): array
    {
        $version = (string) $this->checker->current($package);
        $update = $this->checker->isUpdateAvailable($version, $latest);

        return [
            'package' => $package,
            'version' => $version,
            'latest' => $latest,
            'development' => StableVersions::isDevelopmentBuild($version),
            'update' => $update,
            'release_url' => $update && $latest !== null ? $this->checker->releaseUrl($package, $latest) : null,
        ];
    }

    /**
     * Package of each module installed by Composer, by module name.
     *
     * @return array<string, string>
     */
    private function composerModules(): array
    {
        if (! $this->app->bound('modules')) {
            return [];
        }

        $modules = [];

        foreach ($this->app->make('modules')->all() as $module) {
            $package = $this->packageOf((string) $module->getPath());

            if ($package !== null) {
                $modules[(string) $module->getName()] = $package;
            }
        }

        return $modules;
    }

    /**
     * @return array<string, string>
     */
    private function packagesByPath(): array
    {
        if ($this->packagesByPath !== null) {
            return $this->packagesByPath;
        }

        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            $realPath = $installPath === null ? false : realpath($installPath);

            if ($realPath !== false && ! isset($packages[$realPath])) {
                $packages[$realPath] = $package;
            }
        }

        return $this->packagesByPath = $packages;
    }
}
