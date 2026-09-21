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
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_.]*\/[a-zA-Z0-9][a-zA-Z0-9\-_.]*$/', $repository)) {
            throw new \InvalidArgumentException(
                "Repository must be in 'owner/repo' format with alphanumeric characters, hyphens, underscores, and dots only."
            );
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
        $response = Http::get(sprintf('https://api.github.com/repos/%s/tags', $this->repository));

        if (! $response->successful()) {
            return [];
        }

        $tags = $response->json();

        return array_map(fn (array $tag): mixed => $tag['name'], $tags);
    }

    /**
     * Get repository information.
     */
    public function getRepositoryInfo(): array
    {
        $response = Http::get('https://api.github.com/repos/'.$this->repository);

        if (! $response->successful()) {
            throw new Exception('Unable to fetch repository information for '.$this->repository);
        }

        return $response->json();
    }

    /**
     * Build the download URL for the specified version or latest.
     */
    protected function getDownloadUrl(): string
    {
        if (! in_array($this->version, [null, '', '0'], true)) {
            return sprintf('https://github.com/%s/archive/refs/tags/%s.zip', $this->repository, $this->version);
        }

        $tag = $this->getLatestTag();

        if (! in_array($tag, [null, '', '0'], true)) {
            return sprintf('https://github.com/%s/archive/refs/tags/%s.zip', $this->repository, $tag);
        }

        $branch = $this->getDefaultBranch();

        return sprintf('https://github.com/%s/archive/refs/heads/%s.zip', $this->repository, $branch);
    }

    /**
     * Get the latest tag for the repository.
     *
     * "Latest" means the highest version, not the first tag GitHub happens to
     * return. The API orders tags its own way, so taking the first one served
     * whatever was pushed most recently: a patch on an older line — 1.2.1
     * published after 1.4.0 — would have been handed to everyone scaffolding a
     * theme or plugin, silently downgrading them.
     *
     * Pre-releases lose to any stable version, so tagging a beta does not push
     * it onto people who asked for nothing in particular. They are only chosen
     * when a repository has nothing else, which is the case for a package that
     * has never had a stable release.
     *
     * A tag that is not a version at all — "latest", "nightly" — is left out of
     * the comparison; when none of the tags parse, the first one is returned as
     * before, so a repository using some other scheme keeps working.
     */
    protected function getLatestTag(): ?string
    {
        // 100 rather than the default 30: a repository with more tags than that
        // could have its highest version on the second page, unreachable here.
        $response = Http::get(sprintf('https://api.github.com/repos/%s/tags?per_page=100', $this->repository));

        if (! $response->successful()) {
            return null;
        }

        return $this->selectLatestVersion(array_column((array) $response->json(), 'name'));
    }

    /**
     * Pick the highest version among tag names.
     *
     * Separated from the request so the choice can be tested without standing
     * up HTTP — the ordering is the part that was wrong, not the fetching.
     *
     * @param  array<int, mixed>  $names  Tag names as GitHub returned them
     */
    public function selectLatestVersion(array $names): ?string
    {
        $names = array_values(array_filter(
            $names,
            fn ($name): bool => is_string($name) && $name !== ''
        ));

        if ($names === []) {
            return null;
        }

        $versions = array_values(array_filter($names, $this->looksLikeVersion(...)));

        if ($versions === []) {
            return $names[0];
        }

        $stable = array_values(array_filter($versions, fn (string $name): bool => ! $this->isPreRelease($name)));
        $candidates = $stable === [] ? $versions : $stable;

        usort($candidates, fn (string $a, string $b): int => version_compare(
            $this->normaliseVersion($b),
            $this->normaliseVersion($a)
        ));

        return $candidates[0];
    }

    /**
     * Whether a tag name can be compared as a version at all.
     */
    private function looksLikeVersion(string $name): bool
    {
        return preg_match('/^v?\d+(\.\d+)*(?:[-+.].*)?$/i', $name) === 1;
    }

    /**
     * Whether a tag names a pre-release rather than a finished version.
     */
    private function isPreRelease(string $name): bool
    {
        return preg_match('/-(?:dev|alpha|a|beta|b|rc|pre)\b|-\d*[a-z]/i', $name) === 1;
    }

    /**
     * Strip the leading v so version_compare() sees only the number.
     */
    private function normaliseVersion(string $name): string
    {
        return ltrim($name, 'vV');
    }

    /**
     * Get the default branch name of the repository.
     */
    protected function getDefaultBranch(): string
    {
        $response = Http::get('https://api.github.com/repos/'.$this->repository);

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

        return sprintf('%s-%s-%d.zip', $sanitizedRepo, $version, $timestamp);
    }

    /**
     * Get the temporary file path.
     */
    protected function getTempPath(string $filename): string
    {
        return storage_path('app/tmp/'.$filename);
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
            throw new Exception('Unable to download archive from '.$url);
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
            throw new Exception('Unable to open ZIP archive: '.$tempPath);
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
            throw new Exception('No extracted folder found in '.$extractPath);
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
