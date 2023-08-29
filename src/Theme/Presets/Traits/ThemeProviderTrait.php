<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Traits;

use Illuminate\Support\Str;

trait ThemeProviderTrait
{
    /**
     * Add the asset service provider.
     *
     * @return $this
     */
    protected function addProvider()
    {
        $themeNameSpace = Str::studly($this->getTheme());
        $providerDirectory = app_path('Providers/Theme/'.$themeNameSpace);
        $providerFile = $providerDirectory.'/AssetServiceProvider.php';

        $this->ensureDirectoryExists($providerDirectory);

        copy($this->stubPath("../resources/{$this->exporter->cssFramework}/app/Providers/AssetServiceProvider.stub"), $providerFile);

        $this->replaceInFile(
            '%theme_name%',
            $this->getTheme(),
            $providerFile
        );

        $this->replaceInFile(
            '%theme_namespace%',
            $themeNameSpace,
            $providerFile
        );

        return $this;
    }
}
