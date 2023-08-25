<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Traits;

use Qirolab\Theme\Presets\Traits\HandleFiles;
use Qirolab\Theme\Theme;

trait ViewScaffolding
{
    use HandleFiles;
    use StubTrait;

    public function themePath($path = '')
    {
        return Theme::path($path, $this->theme);
    }

    public function exportViewScaffolding(): void
    {
        $this->exportViews();
    }

    public function exportViews(): self
    {
        $this->ensureDirectoryExists(Theme::path('views', $this->theme));

        $this->copyDirectory(
            __DIR__."/../../stubs/resources/{$this->cssFramework}/views",
            Theme::path('views', $this->theme)
        );

        copy($this->stubPath("../resources/{$this->cssFramework}/index.php"), $this->themePath('index.php'));
        copy($this->stubPath("../resources/{$this->cssFramework}/theme.json"), $this->themePath('theme.json'));
        copy($this->stubPath("../resources/{$this->cssFramework}/style.css"), $this->themePath('style.css'));

        $configData = [
            'theme_name' => $this->themeName,
            'theme_uri' => $this->themeUri,
            'theme_description' => $this->themeDescription,
            'theme_author' => $this->themeAuthor,
            'theme_author_uri' => $this->themeAuthorUri,
            'theme_version' => $this->themeVersion,
        ];

        foreach ($configData as $key => $value) {
            $this->replaceInFile(
                "%{$key}%",
                $value,
                $this->themePath('style.css')
            );
        }

        $themePath = $this->relativeThemePath($this->theme);

        $cssPath = $themePath.'/css/app.css';
        $jsPath = $themePath.'/js/app.js';
        $viteConfig = "@vite(['".$cssPath."', '".$jsPath."'], '".$this->theme."')";

        $this->replaceInFile('%vite%', $viteConfig, Theme::path('views/layouts/app.blade.php', $this->theme));

        return $this;
    }

    protected function publishFiles(array $files): void
    {
        foreach ($files as $file) {
            $publishPath = base_path($file);

            $overwrite = false;

            if (file_exists($publishPath)) {
                $overwrite = $this->confirm(
                    "<fg=red>{$file} already exists.</fg=red>\n ".
                    'Do you want to overwrite?',
                    false
                );
            }

            if (! file_exists($publishPath) || $overwrite) {
                copy(
                    __DIR__.'/../../stubs/'.$file,
                    $publishPath
                );
            }
        }
    }
}
