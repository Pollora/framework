<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Vite;

use Pollen\Theme\Presets\Traits\PresetTrait;
use Pollen\Theme\Presets\Traits\StubTrait;
use Pollen\Theme\Presets\Traits\ThemeConfigTrait;
use Pollen\Theme\Presets\Traits\ThemeProviderTrait;

class TailwindPreset
{
    use PresetTrait;
    use StubTrait;
    use ThemeConfigTrait;
    use ThemeProviderTrait;

    public function export(): void
    {
        $this->exportBootstrapping()->addProvider()->addThemeConfig();
    }

    /**
     * Update the given package array.
     */
    protected static function updatePackageArray(array $packages): array
    {
        return [
            '@tailwindcss/forms' => '^0.5.2',
            'autoprefixer' => '^10.4.7',
            'postcss' => '^8.4.14',
            'postcss-import' => '^14.1.0',
            'tailwindcss' => '^3.1.6',
        ] + $packages;
    }

    /**
     * Update the bootstrapping files.
     *
     * @return $this
     */
    protected function exportBootstrapping()
    {
        $this->ensureDirectoryExists($this->themePath('js'));
        $this->ensureDirectoryExists($this->themePath('css'));
        $this->ensureDirectoryExists($this->themePath('images'));

        copy($this->stubPath('tailwind-stubs/tailwind.config.js'), $this->themePath('tailwind.config.js'));

        $this->replaceInFile(
            '%theme_path%',
            $this->relativeThemePath($this->getTheme()),
            $this->themePath('tailwind.config.js')
        );

        if (! $this->exists($this->themePath('js/app.js'))) {
            copy(
                $this->stubPath('tailwind-stubs/js/app.js'),
                $this->themePath('js/app.js')
            );
        }

        if (! $this->exists($this->themePath('images/pollen.svg'))) {
            copy(
                $this->stubPath('tailwind-stubs/images/pollen.svg'),
                $this->themePath('images/pollen.svg')
            );
        }

        if (! $this->exists($this->themePath('js/bootstrap.js'))) {
            copy(
                $this->stubPath('tailwind-stubs/js/bootstrap.js'),
                $this->themePath('js/bootstrap.js')
            );
        }
        copy($this->stubPath('tailwind-stubs/css/app.css'), $this->themePath('css/app.css'));

        return $this;
    }

    public function getViteConfig()
    {
        return 'css: {
        postcss: {
            plugins: [
                require("tailwindcss")({
                    config: path.resolve(__dirname, "tailwind.config.js"),
                }),
            ],
        },
    },';
    }

    public function updateViteConfig($configData)
    {
        $configData = str_replace('%app_css_input%', 'css/app.css', $configData);

        return str_replace('%css_config%', $this->getViteConfig(), $configData);
    }
}
