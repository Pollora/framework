<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputOption;

trait HasThemeSupport
{
    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions() ?? [], [
            ['theme', null, InputOption::VALUE_OPTIONAL, 'The theme to generate the class in'],
        ]);
    }

    /**
     * Get the theme name from option.
     */
    protected function getThemeOption(): ?string
    {
        return $this->option('theme');
    }

    /**
     * Check if theme option is specified.
     */
    protected function hasThemeOption(): bool
    {
        return $this->option('theme') !== null;
    }

    /**
     * Get the active theme if no theme specified.
     */
    protected function getActiveTheme(): ?string
    {
        if (function_exists('get_stylesheet')) {
            return get_stylesheet();
        }

        return null;
    }

    /**
     * Resolve theme name (from option or active theme).
     */
    protected function resolveTheme(): ?string
    {
        $theme = $this->getThemeOption();
        
        if ($theme === '') {
            // Empty string means use current active theme
            return $this->getActiveTheme();
        }
        
        return $theme;
    }
}
