<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module manifest service inspired by nwidart/laravel-modules.
 *
 * This service is now simplified and only handles Laravel Module (nwidart) specific tasks.
 * Regular themes and plugins now use the self-registration system.
 */
class ModuleManifest
{
    protected Collection $paths;

    protected array $manifest = [];

    protected static ?Collection $manifestData = null;

    public function __construct(
        protected Filesystem $files,
        array $paths,
        protected ?string $manifestPath,
        protected ModuleRepositoryInterface $repository,
        protected ?object $scout = null // Legacy parameter, unused
    ) {
        $this->paths = collect($paths);
    }

    /**
     * Get the current module manifest (service providers).
     *
     * This method is now deprecated as Laravel modules handle their own service providers.
     */
    public function getProviders(): array
    {
        // Legacy method - Laravel modules handle their own service providers
        // through nwidart/laravel-modules package
        return [];
    }

    /**
     * Build the manifest and write it to disk.
     *
     * This method is now deprecated as Laravel modules handle their own manifest.
     */
    public function build(): array
    {
        // Legacy method - Laravel modules handle their own manifest
        // through nwidart/laravel-modules package
        return [];
    }

    /**
     * Register module files.
     *
     * This method is now deprecated as modules handle their own files.
     */
    public function registerFiles(): void
    {
        // Legacy method - Laravel modules handle their own files
        // through nwidart/laravel-modules package
    }

    /**
     * Get modules data from repository and metadata.
     *
     * This method is now deprecated as modules use self-registration.
     */
    public function getModulesData(): Collection
    {
        // Legacy method - modules now use self-registration
        return collect([]);
    }

    /**
     * Reset cached manifest data.
     */
    public static function resetCache(): void
    {
        self::$manifestData = null;
    }

    /**
     * Write the manifest to cache file.
     *
     * This method is now deprecated as Laravel modules handle their own caching.
     */
    public function write(): void
    {
        // Legacy method - Laravel modules handle their own caching
        // through nwidart/laravel-modules package
    }
}
