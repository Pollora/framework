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
        if (! function_exists('wp_get_theme')) {
            return;
        }

        $theme = $this->themeService->theme();

        if (! function_exists('get_stylesheet_directory') || ! $theme) {
            return;
        }

        $parentTheme = $theme->getParentTheme();

        if ($parentTheme) {
            $this->registerPattern($parentTheme);
        }

        $this->registerPattern($theme->getName());

    }

    /**
     * Register patterns from a specific theme.
     *
     * Processes all pattern files found in the specified theme's pattern
     * directory, following the established directory structure.
     *
     * @param  string  $themeName  Name of the theme to process
     *
     * @throws \RuntimeException If WordPress functions are not available
     */
    public function registerPattern(string $themeName): void
    {
        if (! function_exists('wp_get_theme')) {
            return;
        }

        $theme = wp_get_theme($themeName);
        $themeRoot = $theme->get_theme_root();

        $patternDir = $themeRoot.DIRECTORY_SEPARATOR.$themeName.PatternConstants::PATTERN_DIRECTORY;

        // Skip if directory doesn't exist
        if (! is_dir($patternDir)) {
            return;
        }

        $this->registerPatternsFromDirectory($patternDir, $theme);
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
        if ($content === null || $content === '' || $content === '0') {
            return;
        }

        // Create the pattern domain object
        $pattern = Pattern::fromArray($processedData, $content);

        // Register the pattern
        $this->patternRegistrar->registerPattern($pattern);
    }
}
