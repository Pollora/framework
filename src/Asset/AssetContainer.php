<?php

declare(strict_types=1);

namespace Pollen\Asset;

class AssetContainer
{
    protected string $name;

    protected string $hotFile;

    protected string $buildDirectory;

    protected string $manifestPath;

    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;
        $this->hotFile = $config['hot_file'] ?? public_path("{$name}.hot");
        $this->buildDirectory = $config['build_directory'] ?? "build/{$name}";
        $this->manifestPath = $config['manifest_path'] ?? public_path("{$this->buildDirectory}/manifest.json");
        $this->basePath = $config['base_path'] ?? '';
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
}
