<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Application\Services;

use Pollora\BlockPattern\Domain\Contracts\PatternCategoryRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternDataExtractorInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternRegistrarInterface;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\Domain\Contracts\ThemeProviderInterface;
use Pollora\BlockPattern\Domain\Models\Pattern;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;

/**
 * Application service for pattern use cases.
 *
 * This service orchestrates the domain logic and coordinates
 * between the theme provider, data extraction, and registration
 * infrastructure.
 */
class PatternService implements PatternServiceInterface
{
    /**
     * Directory path for pattern files relative to theme root.
     */
    private const PATTERN_DIRECTORY = '/views/patterns/';

    /**
     * Create a new pattern service instance.
     */
    public function __construct(
        private ConfigRepositoryInterface $config,
        private ThemeProviderInterface $themeProvider,
        private PatternDataExtractorInterface $dataExtractor,
        private PatternCategoryRegistrarInterface $categoryRegistrar,
        private PatternRegistrarInterface $patternRegistrar
    ) {}

    /**
     * {@inheritdoc}
     */
    public function registerAll(): void
    {
        $this->registerCategories();
        $this->registerPatterns();
    }

    /**
     * Register all pattern categories from configuration.
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
     */
    private function registerPatterns(): void
    {
        $themes = $this->themeProvider->getActiveThemes();

        foreach ($themes as $theme) {
            if (!method_exists($theme, 'get_stylesheet_directory')) {
                continue;
            }

            $patternDir = $theme->get_stylesheet_directory() . self::PATTERN_DIRECTORY;

            // Skip if directory doesn't exist
            if (!is_dir($patternDir)) {
                continue;
            }

            $this->registerPatternsFromDirectory($patternDir, $theme);
        }
    }

    /**
     * Register patterns from a specific directory.
     *
     * @param string $directory Pattern directory path
     * @param object $theme Theme object
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
     * @param string $file Pattern file path
     * @param object $theme Theme object
     */
    private function processPatternFile(string $file, object $theme): void
    {
        // Extract pattern data from file
        $fileData = $this->dataExtractor->extractFromFile($file);

        // Skip if data is not valid
        if (!$fileData->isValid()) {
            return;
        }

        // Process the raw data
        $processedData = $this->dataExtractor->processData($fileData, $theme);

        // Get the rendered content
        $content = $this->dataExtractor->getContent($file);

        // Skip if content is empty
        if (empty($content)) {
            return;
        }

        // Create the pattern domain object
        $pattern = Pattern::fromArray($processedData, $content);

        // Register the pattern
        $this->patternRegistrar->registerPattern($pattern);
    }
} 