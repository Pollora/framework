<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Traits;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Pollen\Theme\Theme;

trait ThemeConfigTrait
{
    /**
     * Adds the theme configuration.
     *
     * @return $this
     */
    protected function addThemeConfig()
    {

        $this->ensureDirectoryExists(Theme::path('config', $this->getTheme()));

        $this->copyDirectory(
            __DIR__."/../../stubs/resources/{$this->exporter->cssFramework}/config",
            Theme::path('config', $this->getTheme())
        );

        $providerStubPath = Theme::path('config/providers.stub', $this->getTheme());
        $providerPath = Theme::path('config/providers.php', $this->getTheme());

        $themeNameSpace = Str::studly($this->getTheme());

        $this->replaceInFile(
            '%theme_namespace%',
            $themeNameSpace,
            $providerStubPath
        );

        (new Filesystem)->move($providerStubPath, $providerPath);

        return $this;
    }
}
