<?php

declare(strict_types=1);

namespace Pollora\Asset;

/**
 * Manages configuration and paths for a group of related assets.
 *
 * This class handles the configuration settings for asset containers,
 * including paths for hot reloading, build directories, and manifest files.
 */
class AssetContainer
{
    /**
     * The base path for assets in this container.
     *
     * @var string
     */
    public $basePath;

    /**
     * The path to the hot reload file used by Vite.
     */
    protected string $hotFile;

    /**
     * The directory where built assets are stored.
     */
    protected string $buildDirectory;

    /**
     * The path to the Vite manifest file.
     */
    protected string $manifestPath;

    /**
     * Creates a new asset container instance.
     *
     * @param  string  $name  The unique identifier for this container
     * @param  array  $config  Configuration options for the container
     */
    public function __construct(protected string $name, array $config = [])
    {
        $this->hotFile = $config['hot_file'] ?? public_path("{$this->name}.hot");
        $this->buildDirectory = $config['build_directory'] ?? "build/{$this->name}";
        $this->manifestPath = $config['manifest_path'] ?? 'manifest.json';
        $this->basePath = $config['base_path'] ?? '';
    }

    /**
     * Gets the base path for assets in this container.
     *
     * @return string The base path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Gets the container name.
     *
     * @return string The container name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the path to the hot reload file.
     *
     * @return string The hot file path
     */
    public function getHotFile(): string
    {
        return $this->hotFile;
    }

    /**
     * Gets the build directory path.
     *
     * @return string The build directory path
     */
    public function getBuildDirectory(): string
    {
        return $this->buildDirectory;
    }

    /**
     * Gets the path to the manifest file.
     *
     * @return string The manifest file path
     */
    public function getManifestPath(): string
    {
        return $this->manifestPath;
    }

    /**
     * Gets the asset directory configuration.
     *
     * @return array The asset directory configuration
     */
    public function getAssetDir(): array
    {
        return $this->assetDir;
    }
}
