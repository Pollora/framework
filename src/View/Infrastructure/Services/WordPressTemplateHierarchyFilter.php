<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Services;

use Illuminate\Support\Str;
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
        private readonly ResolveBladeTemplateUseCase $resolveBladeTemplateUseCase
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
                    fn ($file): bool => str_contains($file, (string) $template)
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
     * @param  string  $postType  Post type to get templates for
     * @return array<string, string>
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

        $templates = [];

        // This would need access to ViewFinder paths - we'll implement this
        // in a more complete version or inject the ViewFinder paths

        // Cache the results
        if (function_exists('wp_cache_add')) {
            wp_cache_add('pollora/theme_templates', $templates, 'themes');
        }

        return $templates[$postType] ?? [];
    }
}
