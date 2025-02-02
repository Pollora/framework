<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Registrars;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollora\Gutenberg\Helpers\PatternDataProcessor;
use Pollora\Gutenberg\Helpers\PatternValidator;

/**
 * Registrar for block patterns.
 *
 * Handles the discovery and registration of block patterns from theme directories,
 * including processing of pattern data and validation.
 */
class PatternRegistrar
{
    /**
     * Directory path for pattern files relative to theme root.
     */
    private const PATTERN_DIRECTORY = '/views/patterns/';

    /**
     * File extension for pattern files.
     */
    private const PATTERN_FILE_EXTENSION = '.blade.php';

    /**
     * Create a new pattern registrar instance.
     *
     * @param  PatternDataProcessor  $dataProcessor  For processing pattern metadata
     * @param  PatternValidator  $validator  For validating pattern data
     */
    public function __construct(
        protected PatternDataProcessor $dataProcessor,
        protected PatternValidator $validator
    ) {}

    /**
     * Register all valid patterns from active themes.
     */
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

    /**
     * Get active themes for pattern registration.
     *
     * @return array<\WP_Theme> Array of active theme instances
     */
    protected function getThemes(): array
    {
        $stylesheet = get_stylesheet();
        $template = get_template();

        return collect([$stylesheet, $template])
            ->unique()
            ->map(fn ($theme) => wp_get_theme($theme))
            ->all();
    }

    /**
     * Register an individual pattern from a file.
     *
     * @param  string  $file  Path to the pattern file
     * @param  \WP_Theme  $theme  Theme instance the pattern belongs to
     */
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

    /**
     * Get rendered content of a pattern.
     *
     * @param  string  $file  Path to the pattern file
     * @return string|null Rendered pattern content or null if view doesn't exist
     */
    protected function getPatternContent(string $file): ?string
    {
        $viewName = Str::replaceLast(self::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }
}
