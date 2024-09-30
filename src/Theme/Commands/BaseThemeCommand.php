<?php

declare(strict_types=1);

namespace Pollen\Theme\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Pollen\Theme\ThemeMetadata;

abstract class BaseThemeCommand extends Command
{
    protected ThemeMetadata $themeInfo;

    protected Repository $config;

    protected Filesystem $files;

    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct();

        $this->config = $config;
        $this->files = $files;
    }

    protected function directoryExists(): bool
    {
        return $this->files->isDirectory($this->getTheme()->getBasePath());
    }

    protected function getTheme(): ThemeMetadata
    {
        return $this->themeInfo ??= $this->makeTheme($this->argument('name'));
    }

    protected function makeTheme(string $name): ThemeMetadata
    {
        return new ThemeMetadata($name, $this->getThemesPath());
    }

    protected function getThemesPath(): string
    {
        return $this->config->get('theme.directory', base_path('themes'));
    }

    protected function makeFile(string $path, string $content = ''): void
    {
        $fullPath = $this->getTheme()->getBasePath().'/'.$path;
        $this->files->ensureDirectoryExists(dirname($fullPath));
        $this->files->put($fullPath, $content);
    }

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

    protected function getTemplatePath(string $templateName): string
    {
        // Implémentation par défaut
        return realpath(__DIR__.'/../../stubs/'.$templateName);
    }
}
