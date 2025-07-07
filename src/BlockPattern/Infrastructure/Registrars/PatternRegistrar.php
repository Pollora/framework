<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Registrars;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollora\BlockPattern\Domain\Contracts\PatternDataProcessorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternValidatorInterface;

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
     * @param  PatternDataProcessorInterface  $dataProcessor  For processing pattern metadata
     * @param  PatternValidatorInterface  $validator  For validating pattern data
     */
    public function __construct(
        protected PatternDataProcessorInterface $dataProcessor,
        protected PatternValidatorInterface $validator
    ) {}

    /**
     * Register all valid patterns from active themes.
     */
    public function register(): void
    {
        // Skip if required WordPress functions don't exist
        if (! function_exists('get_stylesheet') || ! function_exists('get_template') || ! function_exists('wp_get_theme')) {
            return;
        }

        $themes = $this->getThemes();

        foreach ($themes as $theme) {
            if (! method_exists($theme, 'get_stylesheet_directory')) {
                continue;
            }

            $dirpath = $theme->get_stylesheet_directory().self::PATTERN_DIRECTORY;

            if (! File::isDirectory($dirpath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirpath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $this->registerPattern($file->getPathname(), $theme);
                }
            }
        }
    }

    /**
     * Get active themes for pattern registration.
     *
     * @return array<object> Array of active theme instances
     */
    protected function getThemes(): array
    {
        if (! function_exists('get_stylesheet') || ! function_exists('get_template') || ! function_exists('wp_get_theme')) {
            return [];
        }

        $stylesheet = \get_stylesheet();
        $template = \get_template();

        return $stylesheet === $template
            ? [\wp_get_theme($stylesheet)]
            : [\wp_get_theme($stylesheet), \wp_get_theme($template)];
    }

    /**
     * Register an individual pattern from a file.
     *
     * @param  string  $file  Path to the pattern file
     * @param  object  $theme  Theme instance the pattern belongs to
     */
    protected function registerPattern(string $file, object $theme): void
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

        if (function_exists('register_block_pattern')) {
            \register_block_pattern($patternData['slug'], $patternData);
        }
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
