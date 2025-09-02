<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Infrastructure\Registrars;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Pollora\BlockPattern\Domain\Contracts\PatternDataProcessorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternValidatorInterface;
use Pollora\BlockPattern\Domain\Support\PatternConstants;

/**
 * Registrar for block patterns.
 *
 * Handles the discovery and registration of block patterns from theme directories,
 * including processing of pattern data and validation. This class follows the
 * Domain-Driven Design pattern and integrates with WordPress theme system.
 *
 * @since 1.0.0
 */
class PatternRegistrar
{
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
     *
     * This method discovers and registers block patterns from the active theme
     * and its parent theme (if applicable). It follows WordPress theme hierarchy.
     *
     *
     * @throws \RuntimeException If WordPress functions are not available
     */
    public function register(): void
    {
        // Skip if required WordPress functions don't exist
        if (! function_exists('get_stylesheet') || ! function_exists('get_template') || ! function_exists('wp_get_theme')) {
            return;
        }

        $themes = $this->getActiveThemes();

        foreach ($themes as $theme) {
            $this->registerPatternsFromTheme($theme);
        }
    }

    /**
     * Register patterns from a specific theme.
     *
     * @param  object  $theme  WordPress theme object
     */
    protected function registerPatternsFromTheme(object $theme): void
    {
        $dirpath = $theme->get_stylesheet_directory().PatternConstants::PATTERN_DIRECTORY;

        if (! File::isDirectory($dirpath)) {
            return;
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

    /**
     * Get active themes for pattern registration.
     *
     * Returns both child theme and parent theme (if child theme is active).
     * This ensures patterns from both themes are registered properly.
     *
     * @return array<object> Array of active theme instances
     */
    protected function getActiveThemes(): array
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
     * Processes pattern file headers, validates data, renders content,
     * and registers the pattern with WordPress if valid.
     *
     * @param  string  $file  Path to the pattern file
     * @param  object  $theme  Theme instance the pattern belongs to
     *
     * @throws \InvalidArgumentException If file path is invalid
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
     * Converts the pattern file path to a view name and renders it using
     * Laravel's Blade templating engine if the view exists.
     *
     * @param  string  $file  Path to the pattern file
     * @return string|null Rendered pattern content or null if view doesn't exist
     *
     * @throws \InvalidArgumentException If file path is malformed
     */
    protected function getPatternContent(string $file): ?string
    {
        $viewName = Str::replaceLast(PatternConstants::PATTERN_FILE_EXTENSION, '', Str::after($file, 'views/'));

        return View::exists($viewName) ? View::make($viewName)->render() : null;
    }
}
