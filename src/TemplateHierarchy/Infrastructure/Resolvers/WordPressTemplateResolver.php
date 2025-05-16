<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Infrastructure\Resolvers;

use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\TemplateHierarchy\Domain\Models\TemplateCandidate;
use Pollora\TemplateHierarchy\Infrastructure\Services\AbstractTemplateResolver;

/**
 * Resolver for WordPress templates based on conditional tags.
 */
class WordPressTemplateResolver extends AbstractTemplateResolver
{
    /**
     * The WordPress conditional tag for this resolver.
     */
    private string $condition;

    /**
     * Create a new WordPress template resolver.
     */
    public function __construct(
        string $condition,
        private readonly Repository $config,
        private readonly Filter $filter
    ) {
        $this->condition = $condition;
        $this->origin = 'wordpress';
    }

    /**
     * Check if this resolver applies to the current request.
     */
    public function applies(): bool
    {
        return function_exists($this->condition) && call_user_func($this->condition);
    }

    /**
     * Get template candidates for this resolver.
     *
     * @return TemplateCandidate[]
     */
    public function getCandidates(): array
    {
        $type = $this->conditionToType($this->condition);
        $templates = $this->getTemplatesForType($type);
        $candidates = [];

        foreach ($templates as $template) {
            // Generate both PHP and Blade candidates
            $candidates = array_merge(
                $candidates,
                $this->createPhpAndBladeCandidates($template)
            );

            // Add block template candidates if block theme is active
            if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
                $candidates[] = $this->createBlockThemeCandidate($template);
            }
        }

        return $candidates;
    }

    /**
     * Convert a WordPress condition function name to a template type.
     */
    private function conditionToType(string $condition): string
    {
        $types = array_flip($this->templateTypes());

        return isset($types[$condition]) ? (string) $types[$condition] : str_replace('is_', '', $condition);
    }

    /**
     * Create a block theme template candidate.
     */
    private function createBlockThemeCandidate(string $template): TemplateCandidate
    {
        if (! function_exists('get_block_theme_folders')) {
            return $this->createCandidate('', 'block', 30);
        }

        $blockFolders = get_block_theme_folders();
        $wpTemplatePath = $blockFolders['wp_template'].'/';

        if (str_ends_with($template, '.php')) {
            $blockTemplate = $wpTemplatePath.str_replace('.php', '.html', $template);
        } else {
            $blockTemplate = $wpTemplatePath.$template.'.html';
        }

        return $this->createCandidate($blockTemplate, 'block', 30);
    }

    /**
     * Get templates for a specific template type.
     *
     * @param  string  $type  Template type
     * @return string[] Array of templates
     */
    private function getTemplatesForType(string $type): array
    {
        $templates = match ($type) {
            'single' => $this->singleTemplates(),
            'page' => $this->pageTemplates(),
            'category' => $this->categoryTemplates(),
            'tag' => $this->tagTemplates(),
            'taxonomy' => $this->taxonomyTemplates(),
            'archive' => $this->archiveTemplates(),
            'author' => $this->authorTemplates(),
            'date' => $this->dateTemplates(),
            'home' => ['home.php', 'index.php'],
            'front_page' => ['front-page.php'],
            'singular' => ['singular.php'],
            '404' => ['404.php'],
            'search' => ['search.php'],
            'embed' => ['embed.php'],
            'index' => ['index.php'],
            default => [],
        };

        // Allow plugins to filter templates for each type
        return $this->filter->apply("pollora/template_hierarchy/{$type}_templates", $templates, $this->getQueriedObject());
    }

    /**
     * Get template types and their conditional functions from config.
     *
     * @return array<string, string> Template types with their conditional functions
     */
    private function templateTypes(): array
    {
        // Get WordPress conditional tags from the config
        $conditions = $this->config->get('wordpress.conditions', []);

        // Allow plugins to register additional conditions
        $conditions = $this->filter->apply('pollora/template_hierarchy/conditions', $conditions);

        $templateTypes = [];

        foreach ($conditions as $condition => $value) {
            // If the value is an array, take the first element
            if (is_array($value)) {
                $value = reset($value);
            }

            // Swap key and value
            $templateTypes[$value] = $condition;
        }

        // Add a fallback for the index template
        $templateTypes['index'] = '__return_true';

        return $templateTypes;
    }

    /**
     * Get single post templates.
     *
     * @return string[]
     */
    private function singleTemplates(): array
    {
        $post = $this->getQueriedObject();

        if (! $post || ! isset($post->post_type, $post->post_name)) {
            return ['single.php'];
        }

        return [
            "single-{$post->post_type}-{$post->post_name}.php",
            "single-{$post->post_type}.php",
            'single.php',
        ];
    }

    /**
     * Get page templates.
     *
     * @return string[]
     */
    private function pageTemplates(): array
    {
        $page = $this->getQueriedObject();

        if (! $page || ! isset($page->ID, $page->post_name)) {
            return ['page.php'];
        }

        $templates = [];

        // Check for custom page template
        if (function_exists('get_page_template_slug')) {
            $template = get_page_template_slug($page->ID);
            if ($template) {
                $templates[] = $template;
            }
        }

        $templates[] = "page-{$page->post_name}.php";

        if (isset($page->post_parent) && $page->post_parent) {
            $parent = get_post($page->post_parent);
            if ($parent && isset($parent->post_name)) {
                $templates[] = "page-{$parent->post_name}.php";
            }
        }

        $templates[] = "page-{$page->ID}.php";
        $templates[] = 'page.php';

        return $templates;
    }

    /**
     * Get category templates.
     *
     * @return string[]
     */
    private function categoryTemplates(): array
    {
        $category = $this->getQueriedObject();

        if (! $category || ! isset($category->slug, $category->term_id)) {
            return ['category.php', 'archive.php'];
        }

        return [
            "category-{$category->slug}.php",
            "category-{$category->term_id}.php",
            'category.php',
            'archive.php',
        ];
    }

    /**
     * Get tag templates.
     *
     * @return string[]
     */
    private function tagTemplates(): array
    {
        $tag = $this->getQueriedObject();

        if (! $tag || ! isset($tag->slug, $tag->term_id)) {
            return ['tag.php', 'archive.php'];
        }

        return [
            "tag-{$tag->slug}.php",
            "tag-{$tag->term_id}.php",
            'tag.php',
            'archive.php',
        ];
    }

    /**
     * Get taxonomy templates.
     *
     * @return string[]
     */
    private function taxonomyTemplates(): array
    {
        $term = $this->getQueriedObject();

        if (! $term || ! isset($term->taxonomy, $term->slug)) {
            return ['taxonomy.php', 'archive.php'];
        }

        $taxonomy = $term->taxonomy;

        return [
            "taxonomy-$taxonomy-{$term->slug}.php",
            "taxonomy-$taxonomy.php",
            'taxonomy.php',
            'archive.php',
        ];
    }

    /**
     * Get archive templates.
     *
     * @return string[]
     */
    private function archiveTemplates(): array
    {
        $postType = get_query_var('post_type');
        $templates = [];

        if ($postType) {
            $templates[] = "archive-{$postType}.php";
        }

        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get author templates.
     *
     * @return string[]
     */
    private function authorTemplates(): array
    {
        $author = $this->getQueriedObject();

        if (! $author || ! isset($author->user_nicename, $author->ID)) {
            return ['author.php', 'archive.php'];
        }

        return [
            "author-{$author->user_nicename}.php",
            "author-{$author->ID}.php",
            'author.php',
            'archive.php',
        ];
    }

    /**
     * Get date templates.
     *
     * @return string[]
     */
    private function dateTemplates(): array
    {
        $templates = [];

        if (function_exists('is_day') && is_day()) {
            $templates[] = 'date.php';
            $templates[] = 'day.php';
        } elseif (function_exists('is_month') && is_month()) {
            $templates[] = 'date.php';
            $templates[] = 'month.php';
        } elseif (function_exists('is_year') && is_year()) {
            $templates[] = 'date.php';
            $templates[] = 'year.php';
        }

        $templates[] = 'archive.php';

        return $templates;
    }
}
