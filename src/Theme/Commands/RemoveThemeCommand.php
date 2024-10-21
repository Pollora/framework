<?php

declare(strict_types=1);

namespace Pollora\Theme\Commands;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class RemoveThemeCommand extends BaseThemeCommand
{
    protected $signature = 'theme:remove {name : Name of the theme to remove}';

    protected $description = 'Remove an existing theme';

    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct($config, $files);
    }

    public function handle(): int
    {
        $themeName = $this->argument('name');

        if (! $this->directoryExists()) {
            $this->error("Theme \"{$themeName}\" does not exist.");

            return self::FAILURE;
        }

        if ($this->confirm("Are you sure you want to permanently delete the theme \"{$themeName}\"?")) {
            $this->removeTheme();
            $this->info("Theme \"{$themeName}\" has been removed successfully.");

            return self::SUCCESS;
        }

        $this->info('Theme removal cancelled.');

        return self::SUCCESS;
    }

    protected function removeTheme(): void
    {
        $themePath = $this->getTheme()->getBasePath();
        $this->files->deleteDirectory($themePath);

        $assetsPath = $this->getAssetsPath();
        if ($this->files->isDirectory($assetsPath)) {
            $this->files->deleteDirectory($assetsPath);
        }
    }

    protected function getAssetsPath(): string
    {
        $assetsPath = $this->config->get('theme.assets_path', 'themes');

        return public_path($assetsPath.'/'.$this->getTheme()->getName());
    }
}
