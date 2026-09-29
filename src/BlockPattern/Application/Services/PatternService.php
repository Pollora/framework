<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Application\Services;

use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Domain\Models\Pattern;
use Pollora\BlockPattern\Domain\Support\PatternConstants;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Models\ThemeMetadata;

/**
 * Application service for pattern use cases.
 *
 * This service orchestrates the domain logic and coordinates between the
 * theme provider, data extraction, and registration infrastructure. It handles
 * the complete pattern lifecycle from discovery to WordPress registration,
 * following Domain-Driven Design principles.
 *
 * The service integrates with WordPress theme system to:
 * - Discover pattern files from theme directories
 * - Extract and process pattern metadata
 * - Validate pattern data before registration
 * - Register patterns with WordPress Gutenberg editor
 *
 * @since 1.0.0
 */
class PatternService implements PatternServiceInterface
{
    /**
     * Create a new pattern service instance.
     *
     * @param  ConfigRepositoryInterface  $config  Configuration repository for theme settings
     * @param  PatternDataExtractorInterface  $dataExtractor  Extracts pattern data from files
     * @param  PatternCategoryRegistrarInterface  $categoryRegistrar  Registers pattern categories
     * @param  PatternRegistrarInterface  $patternRegistrar  Registers individual patterns
     * @param  ThemeService  $themeService  Theme management service
     */
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly PatternDataExtractorInterface $dataExtractor,
        private readonly PatternCategoryRegistrarInterface $categoryRegistrar,
        private readonly PatternRegistrarInterface $patternRegistrar,
        private readonly ThemeService $themeService
    ) {}

    /**
     * Register all patterns and categories.
     *
     * This is the main entry point that orchestrates the complete pattern
     * registration process for all active themes.
     */
    public function registerAll(): void
    {
        $this->registerCategories();
        $this->registerPatterns();
    }

    /**
     * Register all pattern categories from configuration.
     *
     * Reads category definitions from theme configuration and registers
     * them with WordPress for use in the block editor.
     */
    private function registerCategories(): void
    {
        $categories = $this->config->get('theme.gutenberg.categories.patterns', []);

        foreach ($categories as $slug => $attributes) {
            $this->categoryRegistrar->registerCategory($slug, $attributes);
        }
    }

    /**
     * Register all patterns from theme directories.
     *
     * Discovers and registers patterns from the active theme and its parent
     * theme (if applicable). Follows WordPress theme hierarchy for proper
     * pattern inheritance.
     */
    private function registerPatterns(): void
    {
        $theme = $this->themeService->theme();

        if (! $theme instanceof ThemeMetadata) {
            return;
        }

        // Ancestors first, so the active theme can override an inherited pattern slug.
        foreach (array_reverse($this->themeService->getParentThemes()) as $parentTheme) {
            $this->registerPatternsFromTheme($parentTheme);
        }

        $this->registerPatternsFromTheme($theme);
    }

    /**
     * Register patterns from a specific theme.
     *
     * Processes all pattern files found in the specified theme's pattern
     * directory, following the established directory structure.
     *
     * @param  string  $themeName  Name of the theme to process
     */
    public function registerPattern(string $themeName): void
    {
        $theme = $this->resolveTheme($themeName);

        if ($theme instanceof ThemeMetadata) {
            $this->registerPatternsFromTheme($theme);
        }
    }

    /**
     * Register every pattern shipped by the given theme.
     *
     * The pattern directory is resolved from the theme metadata rather than from
     * WordPress: `WP_Theme::get_theme_root()` returns the raw theme root and never
     * goes through the `theme_root` filter Pollora installs, so it points at
     * `WP_CONTENT_DIR/themes` instead of the configured `theme.path`.
     */
    private function registerPatternsFromTheme(ThemeMetadata $theme): void
    {
        $patternDir = $theme->getBasePath().PatternConstants::PATTERN_DIRECTORY;

        // Skip if directory doesn't exist
        if (! is_dir($patternDir)) {
            return;
        }

        $this->registerPatternsFromDirectory($patternDir, $this->wordPressTheme($theme));
    }

    /**
     * Find the metadata of a theme by name within the active theme hierarchy.
     *
     * Falls back to a metadata instance built from the active theme's root for
     * themes that are not part of the current hierarchy.
     */
    private function resolveTheme(string $themeName): ?ThemeMetadata
    {
        $activeTheme = $this->themeService->theme();

        $hierarchy = $activeTheme instanceof ThemeMetadata
            ? [$activeTheme, ...$this->themeService->getParentThemes()]
            : $this->themeService->getParentThemes();

        foreach ($hierarchy as $theme) {
            if ($theme->getName() === $themeName) {
                return $theme;
            }
        }

        if (! $activeTheme instanceof ThemeMetadata) {
            return null;
        }

        return new ThemeMetadata($themeName, dirname($activeTheme->getBasePath()));
    }

    /**
     * Get the WordPress theme instance backing a theme, for metadata translation.
     *
     * The theme root is passed explicitly so WordPress resolves the theme from
     * Pollora's theme path instead of its own default one. Returns the metadata
     * itself when WordPress is unavailable; the extractor then skips translation.
     */
    private function wordPressTheme(ThemeMetadata $theme): object
    {
        if (! function_exists('wp_get_theme')) {
            return $theme;
        }

        return \wp_get_theme($theme->getName(), dirname($theme->getBasePath()));
    }

    /**
     * Register patterns from a specific directory.
     *
     * Recursively scans the directory for pattern files and processes each
     * valid file found. Uses RecursiveDirectoryIterator for efficient
     * directory traversal.
     *
     * @param  string  $directory  Pattern directory path
     * @param  object  $theme  WordPress theme object
     *
     * @throws \InvalidArgumentException If directory path is invalid
     */
    private function registerPatternsFromDirectory(string $directory, object $theme): void
    {
        // Get all PHP files in the directory (including subdirectories)
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->processPatternFile($file->getPathname(), $theme);
            }
        }
    }

    /**
     * Process a single pattern file and register it if valid.
     *
     * Extracts pattern metadata, validates the data, renders the content,
     * and registers the pattern with WordPress if all validation passes.
     *
     * @param  string  $file  Pattern file path
     * @param  object  $theme  WordPress theme object
     *
     * @throws \InvalidArgumentException If file path is malformed
     * @throws \RuntimeException If pattern processing fails
     */
    private function processPatternFile(string $file, object $theme): void
    {
        // Extract pattern data from file
        $fileData = $this->dataExtractor->extractFromFile($file);

        // Skip if data is not valid
        if (! $fileData->isValid()) {
            return;
        }

        // Process the raw data
        $processedData = $this->dataExtractor->processData($fileData, $theme);

        // Get the rendered content
        $content = $this->dataExtractor->getContent($file);

        // Skip if content is empty
        if (in_array($content, [null, '', '0'], true)) {
            return;
        }

        // Create the pattern domain object
        $pattern = Pattern::fromArray($processedData, $content);

        // Register the pattern
        $this->patternRegistrar->registerPattern($pattern);
    }
}
