<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\WordPress;

use Pollora\Route\Domain\Contracts\ConditionResolverInterface;
use Pollora\Route\Domain\Contracts\TemplateResolverInterface;
use Pollora\Route\Domain\Models\TemplateHierarchy;
use Pollora\Route\Domain\Services\WordPressContextBuilder;

/**
 * WordPress template resolver implementation
 *
 * Resolves WordPress template hierarchy using native WordPress functions
 * and Blade template system integration.
 */
final class WordPressTemplateResolver implements TemplateResolverInterface
{
    private array $templateHandlers = [];

    private array $templatePaths = [];

    private array $templateExtensions = ['.blade.php', '.php'];

    public function __construct(
        private readonly WordPressContextBuilder $contextBuilder,
        private readonly ConditionResolverInterface $conditionResolver,
        array $config = []
    ) {
        $this->loadConfiguration($config);
    }

    /**
     * Resolve template hierarchy from context
     */
    public function resolveHierarchy(array $context): TemplateHierarchy
    {
        // Use forced condition if provided (for testing)
        if (isset($context['forced_condition'])) {
            return $this->resolveFromCondition(
                $context['forced_condition'],
                $context['condition_parameters'] ?? []
            );
        }

        // Determine template hierarchy from WordPress context
        return $this->resolveFromWordPressContext($context);
    }

    /**
     * Find the first existing template from hierarchy
     */
    public function findTemplate(TemplateHierarchy $hierarchy): ?string
    {
        foreach ($hierarchy->getTemplatesInOrder() as $template) {
            // Try with each extension
            foreach ($this->templateExtensions as $extension) {
                $templateName = $this->normalizeTemplateName($template, $extension);

                if ($this->templateExists($templateName)) {
                    return $templateName;
                }
            }
        }

        return null;
    }

    /**
     * Register a custom template handler for a specific type
     */
    public function registerTemplateHandler(string $type, callable $handler): void
    {
        $this->templateHandlers[$type] = $handler;
    }

    /**
     * Check if a template exists
     */
    public function templateExists(string $template): bool
    {
        // Normalize template name
        $templateName = $this->normalizeTemplateName($template, '');

        // Check Laravel views first (both with and without .blade extension)
        if (function_exists('view') && app()->has('view')) {
            $viewFactory = app('view');

            // Try direct template name
            if ($viewFactory->exists($templateName)) {
                return true;
            }

            // Try with .blade suffix if not already present
            if (!str_ends_with($templateName, '.blade') && $viewFactory->exists($templateName . '.blade')) {
                return true;
            }
        }

        // Check physical file existence
        $extensions = $this->templateExtensions;
        foreach ($extensions as $extension) {
            $templateWithExtension = $templateName . $extension;

            foreach ($this->templatePaths as $path) {
                $fullPath = rtrim($path, '/') . '/' . $templateWithExtension;
                if (file_exists($fullPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get template search paths
     */
    public function getTemplatePaths(): array
    {
        return $this->templatePaths;
    }

    /**
     * Add a template search path
     */
    public function addTemplatePath(string $path, int $priority = 0): void
    {
        $this->templatePaths[$priority] = $path;

        // Sort by priority (highest first)
        krsort($this->templatePaths);
        $this->templatePaths = array_values($this->templatePaths);
    }

    /**
     * Get template extensions in order of preference
     */
    public function getTemplateExtensions(): array
    {
        return $this->templateExtensions;
    }

    /**
     * Set template extensions
     */
    public function setTemplateExtensions(array $extensions): void
    {
        $this->templateExtensions = $extensions;
    }

    /**
     * Resolve hierarchy from specific condition
     */
    private function resolveFromCondition(string $condition, array $parameters = []): TemplateHierarchy
    {
        // Use custom handler if available
        if (isset($this->templateHandlers[$condition])) {
            $handler = $this->templateHandlers[$condition];
            $templates = $handler($parameters);

            return TemplateHierarchy::fromTemplatesArray($templates, $condition, $this);
        }

        // Build hierarchy based on condition
        $templates = $this->buildHierarchyForCondition($condition, $parameters);

        return TemplateHierarchy::fromTemplatesArray($templates, $condition, $this);
    }

    /**
     * Resolve hierarchy from WordPress context
     */
    private function resolveFromWordPressContext(array $context): TemplateHierarchy
    {
        if (is_singular()) {
            $templates = $this->buildSingularHierarchy($context);
        } elseif (is_tax() || is_category() || is_tag()) {
            $templates = $this->buildTaxonomyHierarchy($context);
        } elseif (is_author()) {
            $templates = $this->buildAuthorHierarchy($context);
        } elseif (is_date()) {
            $templates = $this->buildDateHierarchy($context);
        } elseif (is_archive()) {
            $templates = $this->buildArchiveHierarchy($context);
        } elseif (is_home()) {
            $templates = $this->buildHomeHierarchy([]);
        } elseif (is_front_page()) {
            $templates = $this->buildFrontPageHierarchy([]);
        } elseif (is_404()) {
            $templates = ['404', 'index'];
        } elseif (is_search()) {
            $templates = ['search', 'index'];
        } else {
            $templates = ['index'];
        }

        $condition = $this->determineConditionFromWordPress();
        if (function_exists('apply_filters')) {
            $templates = apply_filters("{$condition}_template_hierarchy", $templates);
        }

        return TemplateHierarchy::fromTemplatesArray($templates, $condition, $this);
    }

    /**
     * Resolve using WordPress native functions
     */
    private function resolveFromWordPressNative(array $context): TemplateHierarchy
    {
        $condition = $this->determineConditionFromWordPress();
        $templates = $this->getWordPressTemplateHierarchy($condition);

        return TemplateHierarchy::fromTemplatesArray($templates, $condition, $this);
    }

    /**
     * Resolve using manual detection
     */
    private function resolveFromManualDetection(array $context): TemplateHierarchy
    {
        $condition = $this->determineConditionFromContext($context);
        $templates = $this->buildHierarchyForCondition($condition, []);

        return TemplateHierarchy::fromTemplatesArray($templates, $condition, $this);
    }

    /**
     * Determine WordPress condition from current state using the condition resolver
     *
     * This method uses the configured condition resolver to dynamically detect
     * the current WordPress condition instead of hardcoded if statements.
     */
    private function determineConditionFromWordPress(): string
    {
        // Get all available WordPress conditions from the resolver
        $conditions = $this->conditionResolver->getAvailableConditions();

        // Filter to only WordPress functions (is_* pattern)
        $wpConditions = array_filter($conditions, fn($condition) => str_starts_with((string) $condition, 'is_'));

        // Sort by specificity - more specific conditions first
        $sortedConditions = $this->sortConditionsBySpecificity($wpConditions);

        // Check each condition in order of specificity
        foreach ($sortedConditions as $condition) {
            if ($this->conditionResolver->resolve($condition)) {
                // Convert condition to template slug
                return $this->conditionToTemplateSlug($condition);
            }
        }

        return 'index';
    }

    /**
     * Sort WordPress conditions by specificity (most specific first)
     *
     * @param array $conditions List of WordPress conditions
     * @return array Sorted conditions
     */
    private function sortConditionsBySpecificity(array $conditions): array
    {
        // Define specificity order - more specific conditions first
        $specificityOrder = [
            'is_front_page' => 1000,
            'is_404' => 950,
            'is_page' => 900,
            'is_single' => 850,
            'is_attachment' => 800,
            'is_page_template' => 750,
            'is_category' => 700,
            'is_tag' => 650,
            'is_tax' => 600,
            'is_author' => 550,
            'is_date' => 500,
            'is_day' => 490,
            'is_month' => 480,
            'is_year' => 470,
            'is_time' => 460,
            'is_search' => 450,
            'is_post_type_archive' => 400,
            'is_archive' => 350,
            'is_home' => 300,
            'is_paged' => 250,
        ];

        // Sort conditions by specificity
        usort($conditions, function ($a, $b) use ($specificityOrder) {
            $aScore = $specificityOrder[$a] ?? 0;
            $bScore = $specificityOrder[$b] ?? 0;

            return $bScore <=> $aScore; // Descending order
        });

        return $conditions;
    }

    /**
     * Convert WordPress condition to template slug
     *
     * @param string $condition WordPress condition (e.g., 'is_page')
     * @return string Template slug (e.g., 'page')
     */
    private function conditionToTemplateSlug(string $condition): string
    {
        // Handle special cases
        $specialCases = [
            'is_front_page' => 'front-page',
            'is_404' => '404',
            'is_post_type_archive' => 'archive',
        ];

        if (isset($specialCases[$condition])) {
            return $specialCases[$condition];
        }

        // Remove 'is_' prefix for standard conditions
        return str_replace('is_', '', $condition);
    }

    /**
     * Determine condition from context array
     */
    private function determineConditionFromContext(array $context): string
    {
        if (isset($context['wp_query'])) {
            $wpQuery = $context['wp_query'];

            if (method_exists($wpQuery, 'is_front_page') && $wpQuery->is_front_page()) {
                return 'front-page';
            }

            if (method_exists($wpQuery, 'is_home') && $wpQuery->is_home()) {
                return 'home';
            }

            if (method_exists($wpQuery, 'is_404') && $wpQuery->is_404()) {
                return '404';
            }

            if (method_exists($wpQuery, 'is_page') && $wpQuery->is_page()) {
                return 'page';
            }

            if (method_exists($wpQuery, 'is_single') && $wpQuery->is_single()) {
                return 'single';
            }
        }

        return 'index';
    }

    /**
     * Get WordPress template hierarchy using native functions
     */
    private function getWordPressTemplateHierarchy(string $condition): array
    {
        if (function_exists('get_template_hierarchy')) {
            return get_template_hierarchy($condition, false, '');
        }

        return $this->buildHierarchyForCondition($condition, []);
    }

    /**
     * Build template hierarchy for a specific condition
     */
    private function buildHierarchyForCondition(string $condition, array $parameters): array
    {
        return match ($condition) {
            'front-page' => $this->buildFrontPageHierarchy($parameters),
            'home' => $this->buildHomeHierarchy($parameters),
            'page' => $this->buildSingularHierarchy([]),
            'single' => $this->buildSingularHierarchy([]),
            'category' => $this->buildTaxonomyHierarchy([]),
            'tag' => $this->buildTaxonomyHierarchy([]),
            'archive' => $this->buildArchiveHierarchy([]),
            '404' => $this->build404Hierarchy($parameters),
            'search' => $this->buildSearchHierarchy($parameters),
            default => $this->buildIndexHierarchy($parameters)
        };
    }

    /**
     * Build front page template hierarchy
     */
    private function buildFrontPageHierarchy(array $parameters): array
    {
        return ['front-page', 'home', 'index'];
    }

    /**
     * Build home page template hierarchy
     */
    private function buildHomeHierarchy(array $parameters): array
    {
        return ['home', 'index'];
    }

    /**
     * Build page template hierarchy (legacy support - handled by buildSingularHierarchy)
     */
    private function buildPageHierarchy(array $parameters): array
    {
        // This method is kept for compatibility
        // Page logic is now handled in buildSingularHierarchy
        return $this->buildSingularHierarchy([]);
    }

    /**
     * Build singular template hierarchy using WordPress native get_template_hierarchy()
     */
    private function buildSingularHierarchy(array $context): array
    {
        $post = $this->contextBuilder->extractPostFromContext($context);

        if (! $post) {
            return $this->getWordPressTemplateHierarchy('index');
        }

        if (is_page()) {
            // For pages, use the most specific slug
            $slug = "page-{$post->post_name}";

            return $this->getWordPressTemplateHierarchy($slug);
        } elseif (is_attachment()) {
            // For attachments, use file type if available
            if (function_exists('get_post_mime_type')) {
                $mime_type = get_post_mime_type($post->ID);
                if ($mime_type) {
                    $sub_type = str_replace('/', '_', $mime_type);
                    $slug = "{$sub_type}-{$post->post_name}";

                    return $this->getWordPressTemplateHierarchy($slug);
                }
            }
            $slug = "attachment-{$post->post_name}";

            return $this->getWordPressTemplateHierarchy($slug);
        } else {
            // For singular posts, build enhanced hierarchy with categories
            return $this->buildEnhancedSingleHierarchy($post);
        }
    }

    /**
     * Build enhanced single post hierarchy that includes category-specific templates
     */
    private function buildEnhancedSingleHierarchy($post): array
    {
        $templates = [];

        // Get post categories for enhanced hierarchy
        $categories = get_the_category($post->ID);

        // Add category-specific templates if post has categories
        if (!empty($categories)) {
            foreach ($categories as $category) {
                // Most specific: single-{category}-{postname}
                $templates[] = "single-{$category->slug}-{$post->post_name}";
                // Category specific: single-{category}
                $templates[] = "single-{$category->slug}";
            }
        }

        // Add post-type specific templates
        if ($post->post_type !== 'post') {
            $templates[] = "single-{$post->post_type}-{$post->post_name}";
            $templates[] = "single-{$post->post_type}";
        }

        // Add standard single templates
        $templates[] = "single-{$post->post_name}";
        $templates[] = 'single';
        $templates[] = 'index';

        // Remove duplicates while preserving order
        return array_values(array_unique($templates));
    }

    /**
     * Build taxonomy template hierarchy using WordPress native get_template_hierarchy()
     */
    private function buildTaxonomyHierarchy(array $context): array
    {
        $term = $this->contextBuilder->extractTermFromContext($context);
        if (! $term || ! is_a($term, 'WP_Term')) {
            return $this->getWordPressTemplateHierarchy('archive');
        }

        $taxonomy = $term->taxonomy;

        // Build the most specific slug according to taxonomy type
        if ($taxonomy === 'category') {
            $slug = "category-{$term->slug}";
        } elseif ($taxonomy === 'post_tag') {
            $slug = "tag-{$term->slug}";
        } else {
            $slug = "taxonomy-{$taxonomy}-{$term->slug}";
        }

        return $this->getWordPressTemplateHierarchy($slug);
    }

    /**
     * Build category template hierarchy (legacy support)
     */
    private function buildCategoryHierarchy(array $parameters): array
    {
        return $this->buildTaxonomyHierarchy([]);
    }

    /**
     * Build author template hierarchy using WordPress native get_template_hierarchy()
     */
    private function buildAuthorHierarchy(array $context): array
    {
        $author = $this->contextBuilder->extractUserFromContext($context);
        if (! $author || ! is_a($author, 'WP_User')) {
            return $this->getWordPressTemplateHierarchy('author');
        }

        // Use user_nicename as the most specific slug
        $slug = "author-{$author->user_nicename}";

        return $this->getWordPressTemplateHierarchy($slug);
    }

    /**
     * Build date template hierarchy using WordPress native get_template_hierarchy()
     */
    private function buildDateHierarchy(array $context): array
    {
        // Determine the most specific date type
        if (function_exists('is_day') && is_day()) {
            $slug = 'date-day';
        } elseif (function_exists('is_month') && is_month()) {
            $slug = 'date-month';
        } elseif (function_exists('is_year') && is_year()) {
            $slug = 'date-year';
        } else {
            $slug = 'date';
        }

        return $this->getWordPressTemplateHierarchy($slug);
    }

    /**
     * Build tag template hierarchy (legacy support)
     */
    private function buildTagHierarchy(array $parameters): array
    {
        return $this->buildTaxonomyHierarchy([]);
    }

    /**
     * Build archive template hierarchy using WordPress native get_template_hierarchy()
     */
    private function buildArchiveHierarchy(array $context): array
    {
        // Check if it's a custom post type archive
        $post_type = $context['archive_post_type'] ?? null;

        if (!$post_type && function_exists('get_query_var')) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
        }

        if ($post_type && $post_type !== 'post') {
            $slug = "archive-{$post_type}";

            return $this->getWordPressTemplateHierarchy($slug);
        }

        return $this->getWordPressTemplateHierarchy('archive');
    }

    /**
     * Build 404 template hierarchy
     */
    private function build404Hierarchy(array $parameters): array
    {
        return ['404', 'index'];
    }

    /**
     * Build search template hierarchy
     */
    private function buildSearchHierarchy(array $parameters): array
    {
        return ['search', 'index'];
    }

    /**
     * Build index template hierarchy
     */
    private function buildIndexHierarchy(array $parameters): array
    {
        return ['index'];
    }

    /**
     * Normalize template name for different extensions
     */
    private function normalizeTemplateName(string $template, string $extension): string
    {
        // Remove existing extension if present
        $template = preg_replace('/\.(blade\.)?php$/', '', $template);

        // Add the requested extension
        if ($extension === '.blade.php') {
            return $template;
        }

        return $template.$extension;
    }

    /**
     * Load configuration
     */
    private function loadConfiguration(array $config): void
    {
        if (isset($config['template_paths'])) {
            foreach ($config['template_paths'] as $priority => $path) {
                $this->addTemplatePath($path, $priority);
            }
        }

        if (isset($config['template_extensions'])) {
            $this->setTemplateExtensions($config['template_extensions']);
        }

        if (isset($config['template_handlers'])) {
            foreach ($config['template_handlers'] as $type => $handler) {
                if (is_callable($handler)) {
                    $this->registerTemplateHandler($type, $handler);
                }
            }
        }
    }
}
