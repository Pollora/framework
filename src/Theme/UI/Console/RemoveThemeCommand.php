<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

/**
 * Console command used to remove an existing theme from the filesystem.
 */
class RemoveThemeCommand extends BaseThemeCommand
{
    protected $signature = 'pollora:delete-theme {name : Name of the theme to remove}';

    protected $description = 'Remove an existing theme';

    /**
     * Create a new command instance.
     *
     * @param  Repository  $config  The configuration repository
     * @param  Filesystem  $files   Filesystem instance used for file operations
     */
    public function __construct(Repository $config, Filesystem $files)
    {
        parent::__construct($config, $files);
    }

    /**
     * Execute the command.
     *
     * @return int Command exit code
     */
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

    /**
     * Delete the theme directory and its assets.
     *
     * @return void
     */
    protected function removeTheme(): void
    {
        $themePath = $this->getTheme()->getBasePath();
        $this->files->deleteDirectory($themePath);

        $assetsPath = $this->getAssetsPath();
        if ($this->files->isDirectory($assetsPath)) {
            $this->files->deleteDirectory($assetsPath);
        }
    }

    /**
     * Get the public path where theme assets are stored.
     *
     * @return string The absolute path to the assets directory
     */
    protected function getAssetsPath(): string
    {
        $assetsPath = $this->config->get('theme.assets_path', 'themes');

        return public_path($assetsPath.'/'.$this->getTheme()->getName());
    }
}
