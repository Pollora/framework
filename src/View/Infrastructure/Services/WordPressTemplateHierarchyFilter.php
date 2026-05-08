<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Services;

use Illuminate\Support\Str;
use Illuminate\View\ViewFinderInterface;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;

/**
 * WordPress implementation of template hierarchy filtering.
 *
 * This implementation provides the WordPress-specific logic for
 * extending template hierarchy with Blade templates and handling
 * template resolution according to WordPress conventions.
 */
class WordPressTemplateHierarchyFilter implements TemplateHierarchyFilterInterface
{
    public function __construct(
        private readonly TemplateFinderInterface $templateFinder,
        private readonly ResolveBladeTemplateUseCase $resolveBladeTemplateUseCase,
        private readonly ViewFinderInterface $viewFinder
    ) {}

    /**
     * Extend WordPress template hierarchy to include Blade templates.
     */
    public function extendTemplateHierarchy(array $files): array
    {
        // Get located Blade templates using our TemplateFinderInterface
        $templates = $this->templateFinder->locate($files);

        // Handle block themes if supported
        if (
            function_exists('wp_is_block_theme') &&
            wp_is_block_theme() &&
            function_exists('current_theme_supports') &&
            current_theme_supports('block-templates')
        ) {
            return $this->handleBlockThemeTemplates($templates, $files);
        }

        // For classic themes, prepend Blade templates to original list
        return [...$templates, ...$files];
    }

    /**
     * Resolve template for WordPress template_include filter.
     */
    public function resolveTemplateInclude(string $template): string
    {
        return $this->resolveBladeTemplateUseCase->execute($template);
    }

    /**
     * Add Blade compatibility for theme templates.
     */
    public function extendThemeTemplates(array $templates, $theme, $post, string $postType): array
    {
        if (method_exists($theme, 'load_textdomain') && $theme->load_textdomain()) {
            $theme->get('TextDomain');
        }

        $bladeTemplates = $this->getBladeThemeTemplates($postType);

        return array_merge($templates, $bladeTemplates);
    }

    /**
     * Handle template hierarchy for block themes.
     *
     * @param  array<string>  $templates  Located Blade templates
     * @param  array<string>  $files  Original template files
     * @return array<string>
     */
    private function handleBlockThemeTemplates(array $templates, array $files): array
    {
        $pages = [];

        // Handle custom page templates
        if (function_exists('get_page_template_slug')) {
            $template = get_page_template_slug();
            if ($template) {
                $pages = array_filter(
                    $templates,
                    fn (string $file): bool => str_contains($file, (string) $template)
                );

                $templates = array_diff($templates, $pages);
            }
        }

        // Group templates by base name to avoid duplicates
        return collect([...$pages, ...$files, ...$templates])
            ->groupBy(fn ($item) => Str::of($item)->afterLast('/')->before('.'))
            ->flatten()
            ->toArray();
    }

    /**
     * Get Blade theme templates with Template Name headers.
     *
     * Scans view paths for .blade.php files containing a
     * {{-- Template Name: ... --}} header (WordPress convention).
     *
     * @param  string  $postType  Post type to get templates for
     * @return array<string, string> Relative path => template name
     */
    private function getBladeThemeTemplates(string $postType = ''): array
    {
        // Check cache first
        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get('pollora/theme_templates', 'themes');
            if (is_array($cached) && isset($cached[$postType])) {
                return $cached[$postType];
            }
        }

        $allTemplates = [];

        foreach ($this->viewFinder->getPaths() as $viewPath) {
            if (! is_dir($viewPath)) {
                continue;
            }

            $this->scanDirectoryForTemplates($viewPath, $viewPath, $allTemplates);
        }

        // Filter by post type
        $filtered = [];
        foreach ($allTemplates as $file => $data) {
            if ($postType === '' || in_array($postType, $data['post_types'], true)) {
                $filtered[$file] = $data['name'];
            }
        }

        // Cache per post type
        if (function_exists('wp_cache_set')) {
            $existing = [];
            if (function_exists('wp_cache_get')) {
                $existing = wp_cache_get('pollora/theme_templates', 'themes') ?: [];
            }
            $existing[$postType] = $filtered;
            wp_cache_set('pollora/theme_templates', $existing, 'themes');
        }

        return $filtered;
    }

    /**
     * Scan a directory recursively for Blade templates with Template Name headers.
     *
     * @param  string  $directory  Directory to scan
     * @param  string  $basePath  Base path for relative path calculation
     * @param  array<string, array{name: string, post_types: array<string>}>  $templates  Collected templates
     */
    private function scanDirectoryForTemplates(string $directory, string $basePath, array &$templates): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname(), false, null, 0, 8192);
            if ($content === false) {
                continue;
            }

            // Match: {{-- Template Name: My Template --}}
            if (! preg_match('/\{\{--\s*Template Name:\s*(.+?)\s*--\}\}/', $content, $matches)) {
                continue;
            }

            $relativePath = ltrim(str_replace($basePath, '', $file->getPathname()), '/\\');

            $postTypes = ['page'];
            if (preg_match('/\{\{--\s*Template Post Type:\s*(.+?)\s*--\}\}/', $content, $ptMatches)) {
                $postTypes = array_map('trim', explode(',', $ptMatches[1]));
            }

            $templates[$relativePath] = [
                'name' => trim($matches[1]),
                'post_types' => $postTypes,
            ];
        }
    }
}
