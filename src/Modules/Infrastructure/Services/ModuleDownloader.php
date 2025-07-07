<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use ZipArchive;

/**
 * Generic module downloader service for downloading modules from GitHub repositories.
 *
 * This service handles downloading and extracting modules (themes, plugins) from GitHub
 * repositories. It supports both tagged releases and the latest commit from the default branch.
 *
 * Features:
 * - Downloads from GitHub repositories using the GitHub API
 * - Supports specific version tags or latest release
 * - Extracts to the appropriate module directory
 * - Handles ZIP archive extraction and cleanup
 * - Generic implementation for both themes and plugins
 *
 * Example usage:
 * ```php
 * $downloader = new ModuleDownloader('owner/repo');
 * $extractedPath = $downloader->downloadAndExtract('/path/to/themes');
 * ```
 */
class ModuleDownloader
{
    /**
     * GitHub repository full name (e.g. 'owner/repo').
     */
    protected string $repository;

    /**
     * GitHub repository name only (e.g. 'repo').
     */
    protected string $repositoryName;

    /**
     * Specific version/tag to download (optional).
     */
    protected ?string $version = null;

    /**
     * Create a new ModuleDownloader instance.
     *
     * @param  string  $repository  The repository full name (e.g. 'Pollora/theme-default').
     */
    public function __construct(string $repository)
    {
        if (! str_contains($repository, '/')) {
            throw new Exception("Repository must be in the format 'owner/repo'");
        }

        $this->repository = $repository;
        $this->repositoryName = explode('/', $repository)[1];
    }

    /**
     * Set a specific version/tag to download.
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Downloads the module and extracts it into the destination directory.
     *
     * @param  string  $destination  The base path where the module should be extracted.
     * @return string Path to the extracted module folder.
     *
     * @throws Exception
     */
    public function downloadAndExtract(string $destination): string
    {
        $url = $this->getDownloadUrl();
        $filename = $this->generateTempFilename();
        $tempPath = $this->getTempPath($filename);

        $this->ensureDirectoryExists(dirname($tempPath));

        $this->downloadFile($url, $tempPath);

        $extractPath = $this->prepareExtractionPath($destination);
        $finalPath = $this->extractArchive($tempPath, $extractPath);

        $this->cleanupTempFile($tempPath);

        return $finalPath;
    }

    /**
     * Download the module for a specific ModuleInterface instance.
     */
    public function downloadForModule(ModuleInterface $module, string $destination): string
    {
        $modulePath = rtrim($destination, '/').'/'.$module->getName();

        return $this->downloadAndExtract($modulePath);
    }

    /**
     * Get available releases/tags for the repository.
     */
    public function getAvailableVersions(): array
    {
        $response = Http::get("https://api.github.com/repos/{$this->repository}/tags");

        if (! $response->successful()) {
            return [];
        }

        $tags = $response->json();

        return array_map(fn ($tag): mixed => $tag['name'], $tags);
    }

    /**
     * Get repository information.
     */
    public function getRepositoryInfo(): array
    {
        $response = Http::get("https://api.github.com/repos/{$this->repository}");

        if (! $response->successful()) {
            throw new Exception("Unable to fetch repository information for {$this->repository}");
        }

        return $response->json();
    }

    /**
     * Build the download URL for the specified version or latest.
     */
    protected function getDownloadUrl(): string
    {
        if ($this->version !== null && $this->version !== '' && $this->version !== '0') {
            return "https://github.com/{$this->repository}/archive/refs/tags/{$this->version}.zip";
        }

        $tag = $this->getLatestTag();

        if ($tag !== null && $tag !== '' && $tag !== '0') {
            return "https://github.com/{$this->repository}/archive/refs/tags/{$tag}.zip";
        }

        $branch = $this->getDefaultBranch();

        return "https://github.com/{$this->repository}/archive/refs/heads/{$branch}.zip";
    }

    /**
     * Get the latest tag for the repository.
     */
    protected function getLatestTag(): ?string
    {
        $response = Http::get("https://api.github.com/repos/{$this->repository}/tags");

        if (! $response->successful()) {
            return null;
        }

        $tags = $response->json();

        return $tags[0]['name'] ?? null;
    }

    /**
     * Get the default branch name of the repository.
     */
    protected function getDefaultBranch(): string
    {
        $response = Http::get("https://api.github.com/repos/{$this->repository}");

        if (! $response->successful()) {
            return 'main';
        }

        return $response->json()['default_branch'] ?? 'main';
    }

    /**
     * Generate a temporary filename for the download.
     */
    protected function generateTempFilename(): string
    {
        $sanitizedRepo = str_replace('/', '-', $this->repository);
        $timestamp = time();
        $version = $this->version ?? 'latest';

        return "{$sanitizedRepo}-{$version}-{$timestamp}.zip";
    }

    /**
     * Get the temporary file path.
     */
    protected function getTempPath(string $filename): string
    {
        return storage_path("app/tmp/{$filename}");
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Download file from URL.
     */
    protected function downloadFile(string $url, string $tempPath): void
    {
        $response = Http::withOptions(['stream' => true])->get($url);

        if (! $response->successful()) {
            throw new Exception("Unable to download archive from {$url}");
        }

        file_put_contents($tempPath, $response->body());
    }

    /**
     * Prepare extraction path.
     */
    protected function prepareExtractionPath(string $destination): string
    {
        $extractPath = rtrim($destination, '/').'/'.$this->repositoryName;

        $this->ensureDirectoryExists($extractPath);

        return $extractPath;
    }

    /**
     * Extract ZIP archive.
     */
    protected function extractArchive(string $tempPath, string $extractPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($tempPath) !== true) {
            throw new Exception("Unable to open ZIP archive: {$tempPath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return $this->findExtractedFolder($extractPath);
    }

    /**
     * Find the actual extracted folder (GitHub archives are nested).
     */
    protected function findExtractedFolder(string $extractPath): string
    {
        $contents = scandir($extractPath);
        $subfolder = collect($contents)
            ->filter(fn ($item): bool => $item !== '.' && $item !== '..' && is_dir($extractPath.'/'.$item))
            ->first();

        if (! $subfolder) {
            throw new Exception("No extracted folder found in {$extractPath}");
        }

        return $extractPath.'/'.$subfolder;
    }

    /**
     * Clean up temporary file.
     */
    protected function cleanupTempFile(string $tempPath): void
    {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}
