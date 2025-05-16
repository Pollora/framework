<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Services;

use Illuminate\Contracts\Config\Repository;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Theme\Domain\Contracts\TemplateHierarchyInterface;

/**
 * Class TemplateHierarchy
 *
 * Retrieves the WordPress template hierarchy before page loading.
 * Supports plugin template directories and Blade template conversion.
 */
class TemplateHierarchy implements TemplateHierarchyInterface
{
    /**
     * Store the template hierarchy
     *
     * @var string[]
     */
    private array $templateHierarchy = [];

    /**
     * Whether the template hierarchy has been finalized
     */
    private bool $hierarchyFinalized = false;

    /**
     * Cache for the queried object
     */
    private ?object $queriedObject = null;

    /**
     * Cache for WordPress conditions
     */
    private static ?array $cachedConditions = null;

    /**
     * Cache for plugin conditions
     */
    private static ?array $cachedPluginConditions = null;

    /**
     * Template registry mapping conditions to template getters
     */
    private array $templateRegistry = [];

    /**
     * Create a new TemplateHierarchy instance.
     */
    public function __construct(
        /**
         * The configuration repository
         */
        private readonly Repository $config
    ) {
        // Hook into template_include at a high priority to capture the final template
        $this->filter->add('template_include', [$this, 'captureTemplateInclude'], PHP_INT_MAX - 10);

        // Add early hook to compute hierarchy during template_redirect
        $this->action->add('template_redirect', [$this, 'computeHierarchyEarly'], 0);

        // Initialize the template registry with WordPress core templates
        $this->initializeTemplateRegistry();
    }

    /**
     * Initialize the template registry with WordPress core templates
     */
    private function initializeTemplateRegistry(): void
    {
        $this->templateRegistry = [
            'is_embed' => [$this, 'getEmbedTemplate'],
            'is_404' => [$this, 'get404Template'],
            'is_search' => [$this, 'getSearchTemplate'],
            'is_front_page' => [$this, 'getFrontPageTemplate'],
            'is_home' => [$this, 'getHomeTemplate'],
            'is_privacy_policy' => [$this, 'getPrivacyPolicyTemplate'],
            'is_post_type_archive' => [$this, 'getPostTypeArchiveTemplate'],
            'is_tax' => [$this, 'getTaxonomyTemplate'],
            'is_attachment' => [$this, 'getAttachmentTemplate'],
            'is_single' => [$this, 'getSingleTemplate'],
            'is_page' => [$this, 'getPageTemplate'],
            'is_singular' => [$this, 'getSingularTemplate'],
            'is_category' => [$this, 'getCategoryTemplate'],
            'is_tag' => [$this, 'getTagTemplate'],
            'is_author' => [$this, 'getAuthorTemplate'],
            'is_date' => [$this, 'getDateTemplate'],
            'is_archive' => [$this, 'getArchiveTemplate'],
        ];

        // Allow plugins to register their own template handlers
        $this->templateRegistry = apply_filters('pollora/template_hierarchy/registry', $this->templateRegistry);
    }

    /**
     * Register a custom template handler
     *
     * @param  string  $type  Template type identifier
     * @param  callable  $callback  Function that returns an array of template files
     */
    public function registerTemplateHandler(string $type, callable $callback): void
    {
        $this->filter->add("pollora/template_hierarchy/{$type}_templates", function ($templates) use ($callback): array {
            $customTemplates = call_user_func($callback, $this->queriedObject());

            return array_merge($customTemplates, $templates);
        }, 10, 1);
    }

    /**
     * Compute the template hierarchy early in the WordPress lifecycle
     */
    public function computeHierarchyEarly(): void
    {
        if ($this->templateHierarchy === []) {
            $this->computeHierarchy();
        }
    }

    /**
     * Capture the template being included by WordPress
     */
    public function captureTemplateInclude(string $template): string
    {
        if ($template !== '' && $template !== '0') {
            array_unshift($this->templateHierarchy, $template);
            $this->templateHierarchy = array_unique($this->templateHierarchy);
        }

        return $template;
    }

    /**
     * Get the queried object (with caching)
     */
    private function queriedObject(): ?object
    {
        return $this->queriedObject ??= get_queried_object();
    }

    /**
     * Get the template hierarchy for the current request
     */
    public function hierarchy(bool $refresh = false): array
    {
        if ($refresh || $this->templateHierarchy === [] || ! $this->hierarchyFinalized) {
            $this->computeHierarchy();
        }

        return $this->filter->apply('pollora/template_hierarchy/hierarchy', $this->templateHierarchy);
    }

    /**
     * Generate template hierarchy based on current request
     */
    private function computeHierarchy(): void
    {
        $this->templateHierarchy = [];

        // Process each condition in order
        foreach ($this->templateRegistry as $condition => $templateGetter) {
            if ($this->isConditionSatisfied($condition)) {
                $templates = call_user_func($templateGetter);
                if ($templates !== []) {
                    $this->addTemplatesToHierarchy($templates);
                }
            }
        }

        // Always check index as fallback
        if ($this->templateHierarchy === []) {
            $this->addTemplatesToHierarchy(['index.php']);
        }

        $this->templateHierarchy = array_unique($this->templateHierarchy);
        $this->hierarchyFinalized = true;
    }

    /**
     * Check if a condition function is satisfied
     */
    private function isConditionSatisfied(string $condition): bool
    {
        return function_exists($condition) && call_user_func($condition);
    }

    /**
     * Add templates to the hierarchy with their variants
     */
    private function addTemplatesToHierarchy(array $templates): void
    {
        if ($templates === []) {
            return;
        }

        // Add Blade template variants first
        $this->addBladeTemplateVariants($templates);

        // Then add regular templates
        array_push($this->templateHierarchy, ...$templates);

        // Add block template variants if using a block theme
        if (wp_is_block_theme()) {
            $this->addBlockTemplateVariants($templates);
        }
    }

    // Template getter methods
    private function getEmbedTemplate(): array
    {
        return ['embed.php'];
    }

    private function get404Template(): array
    {
        return ['404.php'];
    }

    private function getSearchTemplate(): array
    {
        return ['search.php'];
    }

    private function getFrontPageTemplate(): array
    {
        return ['front-page.php'];
    }

    private function getHomeTemplate(): array
    {
        return ['home.php', 'index.php'];
    }

    private function getPrivacyPolicyTemplate(): array
    {
        return ['privacy-policy.php'];
    }

    private function getPostTypeArchiveTemplate(): array
    {
        $postType = get_query_var('post_type');
        return $postType ? ["archive-{$postType}.php"] : [];
    }

    private function getTaxonomyTemplate(): array
    {
        $term = $this->queriedObject();
        if (! $term || ! isset($term->taxonomy)) {
            return ['taxonomy.php', 'archive.php'];
        }

        return [
            "taxonomy-{$term->taxonomy}-{$term->slug}.php",
            "taxonomy-{$term->taxonomy}.php",
            'taxonomy.php',
            'archive.php',
        ];
    }

    private function getAttachmentTemplate(): array
    {
        $attachment = $this->queriedObject();
        if (! $attachment) {
            return ['attachment.php'];
        }

        return [
            "attachment-{$attachment->post_mime_type}.php",
            "attachment.php",
        ];
    }

    private function getSingleTemplate(): array
    {
        $post = $this->queriedObject();
        if (! $post) {
            return ['single.php'];
        }

        return [
            "single-{$post->post_type}-{$post->post_name}.php",
            "single-{$post->post_type}.php",
            'single.php',
        ];
    }

    private function getPageTemplate(): array
    {
        $page = $this->queriedObject();
        if (! $page) {
            return ['page.php'];
        }

        $templates = [];

        $template = get_page_template_slug($page->ID);
        if ($template) {
            $templates[] = $template;
        }

        $templates[] = "page-{$page->post_name}.php";
        $templates[] = "page-{$page->ID}.php";
        $templates[] = 'page.php';

        return $templates;
    }

    private function getSingularTemplate(): array
    {
        return ['singular.php'];
    }

    private function getCategoryTemplate(): array
    {
        $category = $this->queriedObject();
        if (! $category) {
            return ['category.php', 'archive.php'];
        }

        return [
            "category-{$category->slug}.php",
            "category-{$category->term_id}.php",
            'category.php',
            'archive.php',
        ];
    }

    private function getTagTemplate(): array
    {
        $tag = $this->queriedObject();
        if (! $tag) {
            return ['tag.php', 'archive.php'];
        }

        return [
            "tag-{$tag->slug}.php",
            "tag-{$tag->term_id}.php",
            'tag.php',
            'archive.php',
        ];
    }

    private function getAuthorTemplate(): array
    {
        $author = $this->queriedObject();
        if (! $author) {
            return ['author.php', 'archive.php'];
        }

        return [
            "author-{$author->user_nicename}.php",
            "author-{$author->ID}.php",
            'author.php',
            'archive.php',
        ];
    }

    private function getDateTemplate(): array
    {
        $templates = [];

        if (is_day()) {
            $templates[] = 'date.php';
            $templates[] = 'day.php';
        } elseif (is_month()) {
            $templates[] = 'date.php';
            $templates[] = 'month.php';
        } elseif (is_year()) {
            $templates[] = 'date.php';
            $templates[] = 'year.php';
        }

        $templates[] = 'archive.php';

        return $templates;
    }

    private function getArchiveTemplate(): array
    {
        return ['archive.php'];
    }

    /**
     * Add Blade template variants to the hierarchy
     */
    private function addBladeTemplateVariants(array $templates): void
    {
        foreach ($templates as $template) {
            if (str_ends_with($template, '.php')) {
                $bladeTemplate = str_replace(['.php', DIRECTORY_SEPARATOR], ['', '.'], $template);
                $this->templateHierarchy[] = $bladeTemplate;
            }
        }
    }

    /**
     * Add block template variants to the hierarchy
     */
    private function addBlockTemplateVariants(array $templates): void
    {
        if (! function_exists('get_block_theme_folders')) {
            return;
        }

        $blockFolders = get_block_theme_folders();
        $wpTemplatePath = $blockFolders['wp_template'].'/';

        foreach ($templates as $template) {
            if (str_ends_with($template, '.php')) {
                $this->templateHierarchy[] = $wpTemplatePath.str_replace('.php', '.html', $template);
            } else {
                $this->templateHierarchy[] = $wpTemplatePath.$template.'.html';
            }
        }
    }

    /**
     * Get the WordPress template hierarchy order from most specific to least specific
     */
    public function getHierarchyOrder(): array
    {
        return array_keys($this->templateRegistry);
    }
}
