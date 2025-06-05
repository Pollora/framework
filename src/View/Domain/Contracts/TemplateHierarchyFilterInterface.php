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
     * @param  mixed  $theme  Current theme object
     * @param  mixed  $post  Current post object
     * @param  string  $postType  Current post type
     * @return array<string, string> Extended templates list
     */
    public function extendThemeTemplates(array $templates, $theme, $post, string $postType): array;
}
