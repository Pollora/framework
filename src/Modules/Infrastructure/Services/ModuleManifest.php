<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module manifest service inspired by nwidart/laravel-modules.
 *
 * This service discovers and manages module metadata from various sources.
 */
class ModuleManifest
{
    protected Filesystem $files;

    protected Collection $paths;

    protected ?string $manifestPath;

    protected array $manifest = [];

    protected static ?Collection $manifestData = null;

    public function __construct(
        Filesystem $files,
        array $paths,
        string $manifestPath,
        protected ModuleRepositoryInterface $repository,
        protected ?object $scout = null // Legacy parameter, unused
    ) {
        $this->files = $files;
        $this->paths = collect($paths);
        $this->manifestPath = $manifestPath;
    }

    /**
     * Get the current module manifest (service providers).
     */
    public function getProviders(): array
    {
        if (! empty($this->manifest)) {
            return $this->manifest;
        }

        return $this->manifest = $this->build();
    }

    /**
     * Build the manifest and write it to disk.
     */
    public function build(): array
    {
        $providers = [];

        // Get providers from modules data first (these don't need autoloading)
        $moduleProviders = $this->getModulesData()
            ->pluck('providers')
            ->flatten()
            ->filter()
            ->toArray();

        $providers = array_merge($providers, $moduleProviders);

        // Legacy scout discovery is no longer used
        // Provider discovery is now handled through the new Discovery system
        // in the appropriate service providers

        return array_unique($providers);
    }

    /**
     * Register module files.
     */
    public function registerFiles(): void
    {
        $this->getModulesData()
            ->each(function (array $manifest) {
                if (empty($manifest['files'])) {
                    return;
                }

                foreach ($manifest['files'] as $file) {
                    $filePath = $manifest['module_directory'].DIRECTORY_SEPARATOR.$file;
                    if (file_exists($filePath)) {
                        include_once $filePath;
                    }
                }
            });
    }

    /**
     * Get modules data from repository and metadata.
     */
    public function getModulesData(): Collection
    {
        if (! empty(self::$manifestData) && ! app()->runningUnitTests()) {
            return self::$manifestData;
        }

        self::$manifestData = collect($this->repository->allEnabled())
            ->map(function ($module) {
                $moduleData = [
                    'name' => $module->getName(),
                    'module_directory' => $module->getPath(),
                    'providers' => [],
                    'files' => [],
                    'priority' => $module->get('priority', 0),
                ];

                // Try to get providers from module metadata
                if (method_exists($module, 'getProviders')) {
                    $moduleData['providers'] = $module->getProviders();
                }

                // Try to get files from module metadata
                if (method_exists($module, 'getFiles')) {
                    $moduleData['files'] = $module->getFiles();
                }

                // Try to discover main service provider if not already defined
                if (empty($moduleData['providers']) && method_exists($module, 'findMainServiceProvider')) {
                    $mainProvider = $module->findMainServiceProvider();
                    if ($mainProvider) {
                        $moduleData['providers'][] = $mainProvider;
                    }
                }

                return $moduleData;
            })
            ->filter(function ($moduleData) {
                // Only include modules that have providers or files
                return ! empty($moduleData['providers']) || ! empty($moduleData['files']);
            })
            ->sortBy(fn ($module) => $module['priority'] ?? 0)
            ->values();

        return self::$manifestData;
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
     */
    public function write(): void
    {
        if (! $this->manifestPath) {
            return;
        }

        $this->files->put(
            $this->manifestPath,
            '<?php return '.var_export($this->getProviders(), true).';'
        );
    }
}
