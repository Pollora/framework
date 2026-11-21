<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use RuntimeException;
use Throwable;
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
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_.]*\/[a-zA-Z0-9][a-zA-Z0-9\-_.]*$/D', $repository)) {
            throw new InvalidArgumentException(
                "Repository must be in 'owner/repo' format with alphanumeric characters, hyphens, underscores, and dots only."
            );
        }

        $this->repository = $repository;
        $this->repositoryName = explode('/', $repository)[1];
    }

    /**
     * Set a specific version/tag to download.
     *
     * The tag ends up in the GitHub archive URL (`archive/refs/tags/<tag>.zip`) and in
     * the name of the temporary file, so it must be a plain tag name: it starts with
     * a letter or a digit and holds only letters, digits, dots, underscores, hyphens
     * and plus signs. No slash, no whitespace, no line break, no "..", nothing that
     * could leave the URL or the file name (`v1.2.0`, `1.0.0-beta.1`, `release-2024`
     * are accepted; a branch name is a valid tag name but has no archive under tags).
     *
     * @throws InvalidArgumentException When the tag is not a plain tag name
     */
    public function setVersion(string $version): self
    {
        if (! $this->isPlainTagName($version)) {
            throw new InvalidArgumentException(sprintf('Invalid version tag "%s": expected a tag name such as v1.2.0 or 1.2.0-beta.1.', $version));
        }

        $this->version = $version;

        return $this;
    }

    /**
     * Largest archive, in bytes, the downloader accepts.
     *
     * A module archive is source code: 100 MiB is far above any real theme or plugin
     * and still bounds what a hostile or broken repository can make us write to disk.
     * Override it in a subclass to change the ceiling.
     */
    protected function maxArchiveBytes(): int
    {
        return 100 * 1024 * 1024;
    }

    /**
     * Whether a transfer in progress is over the ceiling, by its announced or its received size.
     */
    protected function exceedsCeiling(int $announced, int $received): bool
    {
        $max = $this->maxArchiveBytes();

        return $announced > $max || $received > $max;
    }

    /**
     * Whether a name can be put in a github.com URL path and a file name as a tag.
     *
     * The one rule shared by the version asked for ({@see setVersion()}) and the
     * tags GitHub answers with: starts with a letter or a digit, then letters, digits,
     * dots, underscores, hyphens and plus signs, and no "..".
     */
    private function isPlainTagName(string $tag): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]*$/D', $tag) === 1 && ! str_contains($tag, '..');
    }

    /**
     * Downloads the module and extracts it into the destination directory.
     *
     * @param  string  $destination  The base path where the module should be extracted.
     * @return string Path to the extracted module folder.
     *
     * @throws ModuleException
     */
    public function downloadAndExtract(string $destination): string
    {
        $url = $this->getDownloadUrl();
        $filename = $this->generateTempFilename();
        $tempPath = $this->getTempPath($filename);

        $this->ensureDirectoryExists(dirname($tempPath));

        try {
            $this->downloadFile($url, $tempPath);

            $extractPath = $this->prepareExtractionPath($destination);

            return $this->extractArchive($tempPath, $extractPath);
        } finally {
            $this->cleanupTempFile($tempPath);
        }
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
            throw ModuleException::repositoryUnreachable($this->repository);
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

        // The names come from the API, not from the caller: keep only those setVersion() would accept
        $names = array_filter(
            array_column((array) $response->json(), 'name'),
            fn ($name): bool => is_string($name) && $this->isPlainTagName($name)
        );

        return $this->selectLatestVersion(array_values($names));
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
        return preg_match('/^v?\d+(\.\d+)*(?:[-+.].*)?$/iD', $name) === 1;
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

        $branch = $response->json()['default_branch'] ?? 'main';

        // A branch name may hold slashes, but it must stay inside the URL path
        $safe = is_string($branch)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._+\/-]*$/D', $branch) === 1
            && ! str_contains($branch, '..');

        return $safe ? $branch : 'main';
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
     * Download file from URL, straight to disk and within the size ceiling.
     *
     * The archive is streamed to `$tempPath` (a `sink`), never held in memory. The
     * ceiling is enforced three ways: on the `Content-Length` header before the body
     * is read, while the body is read (the header can be absent or false), and on the
     * size of the file once written. An archive over the ceiling is deleted.
     *
     * @throws ModuleException When the download fails or the archive is too large
     */
    protected function downloadFile(string $url, string $tempPath): void
    {
        $max = $this->maxArchiveBytes();
        $tooLarge = false;

        try {
            $response = Http::withOptions([
                'sink' => $tempPath,
                'progress' => function (int $downloadTotal, int $downloaded) use (&$tooLarge): void {
                    if ($this->exceedsCeiling($downloadTotal, $downloaded)) {
                        $tooLarge = true;

                        throw new RuntimeException('The archive is larger than the ceiling.');
                    }
                },
            ])->get($url);
        } catch (Throwable) {
            $this->cleanupTempFile($tempPath);

            throw $tooLarge ? ModuleException::archiveTooLarge($url, $max) : ModuleException::archiveNotDownloaded($url);
        }

        if (! $response->successful()) {
            $this->cleanupTempFile($tempPath);

            throw ModuleException::archiveNotDownloaded($url);
        }

        // The fake client and some proxies skip the callbacks above: the written file has the last word
        $length = $response->header('Content-Length');
        $oversized = ($length !== '' && ctype_digit($length) && (int) $length > $max)
            || (is_file($tempPath) && filesize($tempPath) > $max);

        if ($oversized) {
            $this->cleanupTempFile($tempPath);

            throw ModuleException::archiveTooLarge($url, $max);
        }
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
            throw ModuleException::archiveNotReadable($tempPath);
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
            throw ModuleException::archiveEmpty($extractPath);
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
