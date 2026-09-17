<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared service for scaffolding module directory structures.
 *
 * Handles file operations common to both plugin and theme creation:
 * downloading from repositories, copying with placeholder replacements,
 * text file detection, and directory management.
 */
class ModuleScaffolderService
{
    /**
     * File extensions considered as text for placeholder replacements.
     */
    private const array TEXT_EXTENSIONS = [
        'php', 'js', 'css', 'html', 'htm', 'xml', 'txt', 'md',
        'json', 'yaml', 'yml', 'svg', 'twig', 'blade.php', 'stub',
    ];

    /**
     * MIME types considered as text content.
     */
    private const array TEXT_MIME_TYPES = [
        'text/plain',
        'text/html',
        'text/css',
        'text/javascript',
        'application/javascript',
        'application/json',
        'application/xml',
        'application/x-httpd-php',
    ];

    /**
     * Download a module from a GitHub repository and scaffold it into the target directory.
     *
     * @param  string  $repository  GitHub repository (owner/repo format)
     * @param  string  $basePath  Base path where modules are stored
     * @param  string  $targetPath  Final target directory for the module
     * @param  array<string, string>  $replacements  Placeholder replacements to apply
     * @param  string|null  $version  Specific version/tag to download
     * @param  OutputInterface  $output  Console output for status messages
     * @param  callable|null  $fileFilter  Optional filter: fn(SplFileInfo $file): bool — return false to skip
     * @param  array<string>  $removeDirs  Directories to remove after extraction (e.g. ['bin'])
     * @return bool True if download succeeded, false if fallback is needed
     */
    public function downloadAndScaffold(
        string $repository,
        string $basePath,
        string $targetPath,
        array $replacements,
        ?string $version,
        OutputInterface $output,
        ?callable $fileFilter = null,
        array $removeDirs = []
    ): bool {
        try {
            $downloader = new ModuleDownloader($repository);

            if ($version) {
                $downloader->setVersion($version);
            }

            $output->writeln('<info>Downloading from '.$repository.($version ? sprintf(' (version: %s)', $version) : '').'...</info>');

            $extractedPath = $downloader->downloadAndExtract($basePath);

            $this->ensureDirectoryExists($targetPath);
            $this->copyDirectoryWithReplacements($extractedPath, $targetPath, $replacements, $fileFilter);

            foreach ($removeDirs as $dir) {
                $this->removeDirectory($targetPath.'/'.$dir);
            }

            $this->removeDirectory(dirname($extractedPath));

            $output->writeln('<info>Downloaded and extracted successfully.</info>');

            return true;
        } catch (\Exception $exception) {
            $output->writeln('<error>Failed to download: '.$exception->getMessage().'</error>');
            $output->writeln('<comment>Falling back to generating default structure...</comment>');

            return false;
        }
    }

    /**
     * Copy a directory with placeholder replacements applied to all text files.
     *
     * @param  string  $source  Source directory
     * @param  string  $destination  Destination directory
     * @param  array<string, string>  $replacements  Placeholder replacements
     * @param  callable|null  $fileFilter  Optional filter to exclude files
     */
    public function copyDirectoryWithReplacements(
        string $source,
        string $destination,
        array $replacements,
        ?callable $fileFilter = null
    ): void {
        $this->ensureDirectoryExists($destination);

        foreach (File::allFiles($source) as $item) {
            if ($fileFilter !== null && ! $fileFilter($item)) {
                continue;
            }

            $this->processFileWithReplacements($item, $destination, $replacements);
        }
    }

    /**
     * Copy a directory without replacements.
     */
    public function copyDirectory(string $source, string $destination): void
    {
        $this->ensureDirectoryExists($destination);

        foreach (File::allFiles($source) as $item) {
            $this->processFile($item, $destination);
        }
    }

    /**
     * Copy a single file, applying placeholder replacements to text files.
     *
     * @param  string  $sourcePath  Source file path
     * @param  string  $destinationPath  Destination file path
     * @param  array<string, string>  $replacements  Placeholder replacements
     */
    public function copyFileWithReplacements(string $sourcePath, string $destinationPath, array $replacements): void
    {
        $extension = pathinfo($destinationPath, PATHINFO_EXTENSION);

        if ($this->isTextFile($sourcePath, $extension)) {
            $content = File::get($sourcePath);
            $content = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $content
            );
            File::put($destinationPath, $content);
        } else {
            File::copy($sourcePath, $destinationPath);
        }
    }

    /**
     * Determine the target path for a file, applying stub renaming and replacements.
     *
     * @param  object  $item  SplFileInfo file item
     * @param  string  $destination  Base destination directory
     * @param  string  $relativePath  File's relative path within source
     * @param  array<string, string>  $replacements  Placeholder replacements for filenames
     * @param  callable|null  $pathResolver  Optional: fn(string $relativePath, string $targetDir, string $targetPath): ?array
     * @return array{dir: string, path: string}
     */
    public function getTargetPathInfo(
        object $item,
        string $destination,
        string $relativePath,
        array $replacements,
        ?callable $pathResolver = null
    ): array {
        $targetDir = $destination.($relativePath !== '' && $relativePath !== '0' ? '/'.$relativePath : '');
        $filename = $item->getFilename();

        $filename = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $filename
        );

        $targetPath = $targetDir.'/'.$filename;
        $targetPath = preg_replace('/\.stub$/', '.php', $targetPath);

        if ($pathResolver !== null && str_starts_with($relativePath, 'app/')) {
            $resolved = $pathResolver($relativePath, $targetDir, $targetPath);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return [
            'dir' => $targetDir,
            'path' => $targetPath,
        ];
    }

    /**
     * Handle file copy with optional overwrite confirmation.
     *
     * @param  object  $item  SplFileInfo file item
     * @param  string  $targetPath  Target file path
     * @param  array<string, string>  $replacements  Placeholder replacements
     * @param  bool  $force  Force overwrite without confirmation
     * @param  callable|null  $confirmCallback  fn(string $message): bool — for interactive confirmation
     */
    public function handleFileCopy(
        object $item,
        string $targetPath,
        array $replacements,
        bool $force = false,
        ?callable $confirmCallback = null
    ): void {
        if (File::exists($targetPath) && ! $force && ($confirmCallback !== null && ! $confirmCallback(sprintf('File %s already exists. Do you want to overwrite it?', $targetPath)))) {
            return;
        }

        $this->copyFileWithReplacements($item->getRealPath(), $targetPath, $replacements);
    }

    /**
     * Check if a file is a text file based on extension or MIME type.
     */
    public function isTextFile(string $filePath, string $extension): bool
    {
        if (in_array(strtolower($extension), self::TEXT_EXTENSIONS, true)) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return in_array($mimeType, self::TEXT_MIME_TYPES, true) || str_starts_with($mimeType, 'text/');
    }

    /**
     * Remove a directory recursively.
     */
    public function removeDirectory(string $path): void
    {
        if (is_dir($path)) {
            File::deleteDirectory($path);
        }
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    public function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Process a single file with placeholder replacements.
     */
    private function processFileWithReplacements(object $item, string $destination, array $replacements): void
    {
        $relativePath = $item->getRelativePath();
        $targetInfo = $this->getTargetPathInfo($item, $destination, $relativePath, $replacements);

        $this->ensureDirectoryExists($targetInfo['dir']);

        if ($item->isDir()) {
            $this->copyDirectoryWithReplacements($item->getRealPath(), $targetInfo['path'], $replacements);
        } else {
            $this->copyFileWithReplacements($item->getRealPath(), $targetInfo['path'], $replacements);
        }
    }

    /**
     * Process a single file without replacements.
     */
    private function processFile(object $item, string $destination): void
    {
        $relativePath = $item->getRelativePath();
        $targetInfo = $this->getTargetPathInfo($item, $destination, $relativePath, []);

        $this->ensureDirectoryExists($targetInfo['dir']);

        if ($item->isDir()) {
            $this->copyDirectory($item->getRealPath(), $targetInfo['path']);
        } else {
            File::copy($item->getRealPath(), $targetInfo['path']);
        }
    }
}
