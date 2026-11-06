<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Make a theme the active WordPress theme, with WordPress fully loaded.
 *
 * Meant to run in its own process: during a first install the process that
 * installs WordPress only has it partly loaded (SHORTINIT), where
 * switch_theme() fails on functions that are not defined yet.
 */
#[Description('Activate a WordPress theme')]
#[Signature('pollora:theme:activate {theme : Theme directory name, e.g. my-theme}')]
class ThemeActivateCommand extends Command
{
    public function handle(): int
    {
        if (! $this->isWordPressFullyLoaded()) {
            $this->error('WordPress is not fully loaded: the theme cannot be activated. Run "wp theme activate" instead.');

            return self::FAILURE;
        }

        $theme = (string) $this->argument('theme');

        $this->switchTheme($theme);
        $this->info(sprintf('Theme "%s" is now the active WordPress theme.', $theme));

        return self::SUCCESS;
    }

    protected function isWordPressFullyLoaded(): bool
    {
        return function_exists('switch_theme') && did_action('plugins_loaded') > 0;
    }

    protected function switchTheme(string $theme): void
    {
        switch_theme($theme);
    }
}
