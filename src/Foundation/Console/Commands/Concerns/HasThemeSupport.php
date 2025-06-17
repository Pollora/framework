<?php

declare(strict_types=1);

namespace Pollora\Foundation\Console\Commands\Concerns;

use Pollora\Theme\Domain\Contracts\ThemeDiscoveryInterface;
use Symfony\Component\Console\Input\InputOption;

trait HasThemeSupport
{
    /**
     * Get the console command options for theme support.
     *
     * @return array The theme-related command options
     */
    protected function getThemeOptions(): array
    {
        return [
            ['theme', null, InputOption::VALUE_OPTIONAL, 'The theme to generate the class in'],
        ];
    }

    /**
     * Get the theme name from option.
     *
     * @return string|null The theme name if specified
     */
    protected function getThemeOption(): ?string
    {
        return $this->option('theme');
    }

    /**
     * Check if theme option is specified.
     *
     * @return bool True if theme option is set
     */
    protected function hasThemeOption(): bool
    {
        return $this->option('theme') !== null || $this->input->hasParameterOption(['--theme']);
    }

    /**
     * Get the active theme if no theme specified.
     *
     * @return string|null The active theme name
     */
    protected function getActiveTheme(): ?string
    {
        if (! property_exists($this, 'discovery') || $this->discovery === null) {
            $this->discovery = app(ThemeDiscoveryInterface::class);
        }

        $activeTheme = $this->discovery?->getActiveTheme();

        if ($activeTheme) {
            return $activeTheme->getName();
        }

        return null;
    }

    /**
     * Resolve theme name (from option or active theme).
     *
     * @return string|null The resolved theme name
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
