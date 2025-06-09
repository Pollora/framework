<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Pollora\Theme\Domain\Models\ThemeMetadata;

/**
 * Base command class used by theme related CLI commands.
 *
 * Provides common helper methods for working with themes on the
 * filesystem.
 */
abstract class BaseThemeCommand extends Command
{
    /**
     * Cached ThemeMetadata instance for the current command run.
     */
    protected ThemeMetadata $themeInfo;

    /**
     * Create a new command instance.
     *
     * @param  Repository  $config  The configuration repository
     * @param  Filesystem  $files  Filesystem instance used for file operations
     */
    public function __construct(protected Repository $config, protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Determine if the theme directory already exists.
     *
     * @return bool True when the directory exists
     */
    protected function directoryExists(): bool
    {
        return $this->files->isDirectory($this->getTheme()->getBasePath());
    }

    /**
     * Get the ThemeMetadata instance for the theme being handled.
     *
     * @return ThemeMetadata The theme metadata object
     */
    protected function getTheme(): ThemeMetadata
    {
        return $this->themeInfo ??= $this->makeTheme($this->argument('name'));
    }

    /**
     * Create a ThemeMetadata object for the given name.
     *
     * @param  string  $name  Name of the theme
     * @return ThemeMetadata The created theme metadata
     */
    protected function makeTheme(string $name): ThemeMetadata
    {
        return new ThemeMetadata($name, $this->getThemesPath());
    }

    /**
     * Get the base directory where themes are stored.
     *
     * @return string The absolute path to the themes directory
     */
    protected function getThemesPath(): string
    {
        return $this->config->get('theme.directory', base_path('themes'));
    }

    /**
     * Create a file within the theme directory.
     *
     * @param  string  $path  Path relative to the theme base directory
     * @param  string  $content  Optional file contents
     */
    protected function makeFile(string $path, string $content = ''): void
    {
        $fullPath = $this->getTheme()->getBasePath().'/'.$path;
        $this->files->ensureDirectoryExists(dirname($fullPath));
        $this->files->put($fullPath, $content);
    }

    /**
     * Generate file contents from a stub template.
     *
     * @param  string  $templateName  The stub template filename
     * @param  array<string, string>  $replacements  Key-value replacements within the stub
     * @return string The populated template content
     */
    protected function fromTemplate(string $templateName, array $replacements = []): string
    {
        $templatePath = $this->getTemplatePath($templateName);
        $content = $this->files->get($templatePath);

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }

    /**
     * Resolve the path to a stub template.
     *
     * @param  string  $templateName  Template file name
     * @return string Absolute path to the stub file
     */
    protected function getTemplatePath(string $templateName): string
    {
        // Default implementation
        return realpath(__DIR__.'/../../stubs/'.$templateName);
    }
}
