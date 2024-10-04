<?php

declare(strict_types=1);

namespace Pollen\Gutenberg\Registrars;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollen\Gutenberg\Helpers\PatternDataProcessor;
use Pollen\Gutenberg\Helpers\PatternValidator;

class PatternRegistrar
{
    private const PATTERN_DIRECTORY = '/views/patterns/';

    private const PATTERN_FILE_EXTENSION = '.blade.php';

    public function __construct(
        protected PatternDataProcessor $dataProcessor,
        protected PatternValidator $validator
    ) {}

    public function register(): void
    {
        $themes = $this->getThemes();

        foreach ($themes as $theme) {
            $dirpath = $theme->get_stylesheet_directory().self::PATTERN_DIRECTORY;

            if (! File::isDirectory($dirpath)) {
                continue;
            }

            collect(File::glob($dirpath.'*'.self::PATTERN_FILE_EXTENSION))
                ->each(fn ($file) => $this->registerPattern($file, $theme));
        }
    }

    protected function getThemes(): array
    {
        $stylesheet = get_stylesheet();
        $template = get_template();

        return collect([$stylesheet, $template])
            ->unique()
            ->map(fn ($theme) => wp_get_theme($theme))
            ->all();
    }

    protected function registerPattern(string $file, \WP_Theme $theme): void
    {
        $patternData = $this->dataProcessor->getPatternData($file);

        if (! $this->validator->isValid($patternData, $file)) {
            return;
        }

        $patternData = $this->dataProcessor->process($patternData, $theme);

        $content = $this->getPatternContent($file);

        if ($content === null || $content === '' || $content === '0') {
            return;
        }

        $patternData['content'] = $content;

        register_block_pattern($patternData['slug'], $patternData);
    }

    protected function getPatternContent(string $file): ?string
    {
        $viewName = Str::replaceLast(self::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }
}
