<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Repositories;

/**
 * Infrastructure class for managing configuration and paths for a group of related assets.
 *
 * Handles configuration settings for asset containers, including paths for hot reloading,
 * build directories, manifest files, and base asset paths. Used by infrastructure services
 * to resolve asset locations and integration with tools like Vite.
 */
class AssetContainer
{
    /**
     * Array of asset directory paths.
     * @var array
     */
    public array $assetDir;
    /**
     * The base path for assets in this container.
     * @var string
     */
    public string $basePath;

    /**
     * The path to the hot reload file used by Vite.
     * @var string
     */
    protected string $hotFile;

    /**
     * The directory where built assets are stored.
     * @var string
     */
    protected string $buildDirectory;

    /**
     * The path to the Vite manifest file.
     * @var string
     */
    protected string $manifestPath;

    /**
     * Creates a new asset container instance.
     *
     * @param string $name The unique identifier for this container
     * @param array $config Configuration options for the container
     */
    public function __construct(protected string $name, protected array $config = [])
    {
        $this->hotFile = $config['hot_file'] ?? public_path("{$this->name}.hot");
        $this->buildDirectory = $config['build_directory'] ?? "build/{$this->name}";
        $this->manifestPath = $config['manifest_path'] ?? 'manifest.json';
        $this->basePath = $config['base_path'] ?? '';
    }

    /**
     * Gets the base path for assets in this container.
     * @return string
     */
    public function getBasePath(): string { return $this->basePath; }
    /**
     * Gets the unique name of this asset container.
     * @return string
     */
    public function getName(): string { return $this->name; }
    /**
     * Gets the path to the hot reload file used by Vite.
     * @return string
     */
    public function getHotFile(): string { return $this->hotFile; }
    /**
     * Gets the build directory for assets.
     * @return string
     */
    public function getBuildDirectory(): string { return $this->buildDirectory; }
    /**
     * Gets the path to the Vite manifest file.
     * @return string
     */
    public function getManifestPath(): string { return $this->manifestPath; }
    /**
     * Gets the asset directory array.
     * @return array
     */
    public function getAssetDir(): array { return $this->assetDir; }
}
