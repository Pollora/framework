<?php

declare(strict_types=1);

namespace Pollora\Theme;

/**
 * Class TemplateHierarchy
 *
 * Retrieves the WordPress template hierarchy before page loading.
 */
class TemplateHierarchy
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
     *
     * @var array|null
     */
    private static ?array $cachedConditions = null;

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Register a custom template handler
     *
     * @param string $type Template type identifier
     * @param callable $callback Function that returns an array of template files
     * @return void
     */
    public function registerTemplateHandler(string $type, callable $callback): void
    {
        add_filter("pollora_template_hierarchy/{$type}_templates", function($templates) use ($callback) {
            $customTemplates = call_user_func($callback, $this->queriedObject());
            return array_merge($customTemplates, $templates);
        }, 10, 1);
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        // Hook into template_include to detect custom templates from plugins
        add_filter('template_include', [$this, 'captureTemplateInclude'], PHP_INT_MAX - 10);
    }

    /**
     * Capture the template being included by WordPress
     *
     * @param string $template The template being included
     * @return string The unchanged template path
     */
    public function captureTemplateInclude(string $template): string
    {
        // Add the final template to the beginning of our hierarchy
        // This ensures plugin templates take precedence
        if (!empty($template)) {
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
     *
     * @param bool $refresh Force recomputing the hierarchy even if already calculated
     * @return string[] The template hierarchy
     */
    public function hierarchy(bool $refresh = false): array
    {
        // Only compute hierarchy if not already done or if refresh is requested
        if ($refresh || empty($this->templateHierarchy) || !$this->hierarchyFinalized) {
            $this->computeHierarchy();
        }

        // Allow other plugins to filter the final hierarchy
        return apply_filters('pollora/template_hierarchy/hierarchy', $this->templateHierarchy);
    }

    /**
     * Generate template hierarchy based on current request
     */
    private function computeHierarchy(): void
    {
        // Get the WordPress template hierarchy order
        $hierarchyOrder = self::getHierarchyOrder();

        // Create a temporary array to store templates by condition
        $templatesByCondition = [];

        // Check each condition in the hierarchy order and collect templates
        foreach ($hierarchyOrder as $condition) {
            // Skip the fallback condition
            if ($condition === '__return_true') {
                continue;
            }

            if ($this->isConditionSatisfied($condition)) {
                $type = $this->conditionToType($condition);
                $templates = $this->getTemplatesForType($type);

                if (!empty($templates)) {
                    $templatesByCondition[$condition] = $templates;
                }
            }
        }

        // Process collected templates in hierarchy order
        foreach ($hierarchyOrder as $condition) {
            if (isset($templatesByCondition[$condition])) {
                $this->addTemplatesToHierarchy($templatesByCondition[$condition]);
            }
        }

        // Always check index as fallback
        $this->addTemplatesToHierarchy($this->getTemplatesForType('index'));

        // Ensure the hierarchy is unique
        $this->templateHierarchy = array_unique($this->templateHierarchy);
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
     *
     * @param string[] $templates
     */
    private function addTemplatesToHierarchy(array $templates): void
    {
        if (empty($templates)) {
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

    /**
     * Convert a WordPress condition function name to a template type.
     */
    private function conditionToType(string $condition): string
    {
        $types = array_flip($this->templateTypes());
        return $types[$condition] ?? str_replace('is_', '', $condition);
    }

    /**
     * Add Blade template variants to the hierarchy
     *
     * @param  string[]  $templates  Regular PHP templates
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
     *
     * @param  string[]  $templates  Regular PHP templates
     */
    private function addBlockTemplateVariants(array $templates): void
    {
        if (!function_exists('get_block_theme_folders')) {
            return;
        }

        $blockFolders = get_block_theme_folders();
        $wpTemplatePath = $blockFolders['wp_template'] . '/';

        foreach ($templates as $template) {
            if (str_ends_with($template, '.php')) {
                $this->templateHierarchy[] = $wpTemplatePath . str_replace('.php', '.html', $template);
            } else {
                // Block theme custom template (no suffix)
                $this->templateHierarchy[] = $wpTemplatePath . $template . '.html';
            }
        }
    }

    /**
     * Get templates for a specific template type
     *
     * @param  string  $type  Template type
     * @return string[] Array of templates
     */
    private function getTemplatesForType(string $type): array
    {
        $templates = match($type) {
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
        return apply_filters("pollora/template_hierarchy/{$type}_templates", $templates, $this->queriedObject());
    }

    /**
     * Get single post templates
     *
     * @return string[]
     */
    private function singleTemplates(): array
    {
        $post = $this->queriedObject();

        if (!$post) {
            return ['single.php'];
        }

        return [
            "single-{$post->post_type}-{$post->post_name}.php",
            "single-{$post->post_type}.php",
            'single.php',
        ];
    }

    /**
     * Get page templates
     *
     * @return string[]
     */
    private function pageTemplates(): array
    {
        $page = $this->queriedObject();

        if (!$page) {
            return ['page.php'];
        }

        $templates = [];

        $template = get_page_template_slug($page->ID);
        if ($template) {
            $templates[] = $template;
        }

        $templates[] = "page-{$page->post_name}.php";

        if ($page->post_parent) {
            $parent = get_post($page->post_parent);
            $templates[] = "page-{$parent->post_name}.php";
        }

        $templates[] = "page-{$page->ID}.php";
        $templates[] = 'page.php';

        return $templates;
    }

    /**
     * Get category templates
     *
     * @return string[]
     */
    private function categoryTemplates(): array
    {
        $category = $this->queriedObject();

        if (!$category) {
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
     * Get tag templates
     *
     * @return string[]
     */
    private function tagTemplates(): array
    {
        $tag = $this->queriedObject();

        if (!$tag) {
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
     * Get taxonomy templates
     *
     * @return string[]
     */
    private function taxonomyTemplates(): array
    {
        $term = $this->queriedObject();

        if (!$term || !isset($term->taxonomy)) {
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
     * Get archive templates
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
     * Get author templates
     *
     * @return string[]
     */
    private function authorTemplates(): array
    {
        $author = $this->queriedObject();

        if (!$author) {
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
     * Get date templates
     *
     * @return string[]
     */
    private function dateTemplates(): array
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

    /**
     * Get WordPress conditions from config with plugin extensions
     *
     * @return array The conditions from config with any plugin additions
     */
    private function getConditions(): array
    {
        // Use cached conditions if available
        if (self::$cachedConditions !== null) {
            return self::$cachedConditions;
        }

        // Retrieve the WordPress conditions from the Laravel config
        $conditions = config('wordpress.conditions', []);

        // Allow plugins to register additional conditions
        $conditions = apply_filters('pollora/template_hierarchy/conditions', $conditions);

        // Cache the result
        self::$cachedConditions = $conditions;

        return $conditions;
    }

    /**
     * Get template types and their conditional functions from config
     *
     * @return array<string, string> Template types with their conditional functions
     */
    private function templateTypes(): array
    {
        $conditions = $this->getConditions();
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
     * Get the WordPress template hierarchy order from most specific to least specific
     *
     * @return string[] Array of conditional function names in order of specificity
     */
    public static function getHierarchyOrder(): array
    {
        // Use the singleton instance to get conditions
        $instance = self::instance();
        $conditions = $instance->getConditions();

        $hierarchyOrder = array_keys($conditions);

        // Allow plugins to modify the hierarchy order
        $hierarchyOrder = apply_filters('pollora/template_hierarchy/order', $hierarchyOrder);

        $hierarchyOrder[] = '__return_true';

        return $hierarchyOrder;
    }
}
