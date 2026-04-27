<?php

declare(strict_types=1);

namespace Pollora\View\Domain\Contracts;

/**
 * Contract for WordPress template hierarchy filtering.
 *
 * This interface defines the capabilities needed to extend WordPress
 * template hierarchy with Blade templates and handle template resolution.
 */
interface TemplateHierarchyFilterInterface
{
    /**
     * Extend WordPress template hierarchy to include Blade templates.
     *
     * @param  array<string>  $files  Original template files from WordPress
     * @return array<string> Extended list including Blade templates
     */
    public function extendTemplateHierarchy(array $files): array;

    /**
     * Resolve template for WordPress template_include filter.
     *
     * @param  string  $template  WordPress template file path
     * @return string Template path to use (original or modified)
     */
    public function resolveTemplateInclude(string $template): string;

    /**
     * Add Blade compatibility for theme templates.
     *
     * @param  array<string, string>  $templates  Existing theme templates
     * @param  \WP_Theme  $theme  Current theme object
     * @param  \WP_Post|null  $post  Current post object
     * @param  string  $postType  Current post type
     * @return array<string, string> Extended templates list
     */
    public function extendThemeTemplates(array $templates, \WP_Theme $theme, ?\WP_Post $post, string $postType): array;
}
