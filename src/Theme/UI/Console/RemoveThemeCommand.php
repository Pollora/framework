<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;

/**
 * Console command used to remove an existing theme from the filesystem.
 */
#[Description('Remove an existing theme')]
#[Signature('pollora:theme:delete {name : Name of the theme to remove}')]
class RemoveThemeCommand extends BaseThemeCommand
{
    /**
     * Execute the command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $themeName = $this->argument('name');

        if (! $this->directoryExists()) {
            $this->error(sprintf('Theme "%s" does not exist.', $themeName));

            return self::FAILURE;
        }

        if ($this->confirm(sprintf('Are you sure you want to permanently delete the theme "%s"?', $themeName))) {
            $this->removeTheme();
            $this->info(sprintf('Theme "%s" has been removed successfully.', $themeName));

            return self::SUCCESS;
        }

        $this->info('Theme removal cancelled.');

        return self::SUCCESS;
    }

    /**
     * Delete the theme directory and its assets.
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
