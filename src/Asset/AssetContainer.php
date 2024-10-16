<?php

declare(strict_types=1);

namespace Pollen\Asset;

class AssetContainer
{
    public $basePath;
    protected string $hotFile;
    protected string $buildDirectory;
    protected string $manifestPath;
    protected array $assetDir;

    public function __construct(protected string $name, array $config = [])
    {
        $this->hotFile = $config['hot_file'] ?? public_path("{$this->name}.hot");
        $this->buildDirectory = $config['build_directory'] ?? "build/{$this->name}";
        $this->manifestPath = $config['manifest_path'] ?? public_path("{$this->buildDirectory}/manifest.json");
        $this->basePath = $config['base_path'] ?? '';
        $this->assetDir = $config['asset_dir'] ?? '';
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHotFile(): string
    {
        return $this->hotFile;
    }

    public function getBuildDirectory(): string
    {
        return $this->buildDirectory;
    }

    public function getManifestPath(): string
    {
        return $this->manifestPath;
    }

    public function getAssetDir(): array
    {
        return $this->assetDir;
    }
}
