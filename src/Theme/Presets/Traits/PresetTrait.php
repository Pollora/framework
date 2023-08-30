<?php

declare(strict_types=1);

namespace Pollen\Theme\Presets\Traits;

use Pollen\Theme\Presets\Vite\VitePresetExport;
use Pollen\Theme\Theme;
use Qirolab\Theme\Presets\Traits\HandleFiles;

trait PresetTrait
{
    use HandleFiles;

    /**
     * @var VitePresetExport
     */
    public $exporter;

    public function __construct(VitePresetExport $exporter)
    {
        $this->exporter = $exporter;
    }

    public function getTheme(): string
    {
        return $this->exporter->getTheme();
    }

    public function themePath($path = '')
    {
        return Theme::path($path, $this->getTheme());
    }

    public function jsPreset()
    {
        return $this->exporter->jsPreset();
    }
}
