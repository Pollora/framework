<?php

declare(strict_types=1);

namespace Pollora\Theme;

/**
 * Class TemplateHierarchy
 *
 * Retrieves the WordPress template hierarchy before page loading.
 * Supports plugin template directories and Blade template conversion.
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
     * Cache for plugin conditions
     *
     * @var array|null
     */
    private static ?array $cachedPluginConditions = null;

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
        add_filter("pollora/template_hierarchy/{$type}_templates", function($templates) use ($callback) {
            $customTemplates = call_user_func($callback, $this->queriedObject());
            return array_merge($customTemplates, $templates);
        }, 10, 1);
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        // Hook into template_include at a high priority to capture the final template
        // This filter is applied after WordPress has determined the template to use
        add_filter('template_include', [$this, 'captureTemplateInclude'], PHP_INT_MAX - 10);

        // Add early hook to compute hierarchy during template_redirect
        // This happens before WordPress starts looking for templates
        add_action('template_redirect', [$this, 'computeHierarchyEarly'], 0);
    }

    /**
     * Compute the template hierarchy early in the WordPress lifecycle
     *
     * This ensures our hierarchy is ready before WordPress starts its template selection
     */
    public function computeHierarchyEarly(): void
    {
        // Compute the hierarchy if not already done
        if (empty($this->templateHierarchy)) {
            $this->computeHierarchy();
        }
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
        // Reset hierarchy before computing
        $this->templateHierarchy = [];

        // Collect all active WooCommerce templates first
        $wooCommerceTemplatesAdded = $this->collectWooCommerceTemplates();

        // Now collect all WordPress templates that apply to the current request
        $this->collectWordPressTemplates();

        // Always check index as fallback if no template was found
        if (empty($this->templateHierarchy)) {
            $this->addTemplatesToHierarchy($this->getTemplatesForType('index'));
        }

        // Ensure the hierarchy is unique and maintain proper order
        $this->templateHierarchy = array_unique($this->templateHierarchy);

        // Mark hierarchy as finalized
        $this->hierarchyFinalized = true;
    }

    /**
     * Collect templates for WooCommerce pages
     *
     * @return bool True if any WooCommerce templates were added
     */
    private function collectWooCommerceTemplates(): bool
    {
        // Early bail if WooCommerce is not active
        if (!function_exists('is_woocommerce')) {
            return false;
        }

        $initialCount = count($this->templateHierarchy);
        $templates = [];

        // Product Category Archives
        if (function_exists('is_product_category') && is_product_category()) {
            $term = $this->queriedObject();

            if ($term && isset($term->slug)) {
                // Current category template
                $templates[] = "woocommerce/taxonomy-product_cat-{$term->slug}.php";

                // Try parent category templates if available
                if (isset($term->parent) && $term->parent) {
                    $parent = get_term($term->parent, 'product_cat');
                    if ($parent && !is_wp_error($parent)) {
                        $templates[] = "woocommerce/taxonomy-product_cat-{$parent->slug}.php";
                    }
                }
            }

            // Generic category template
            $templates[] = "woocommerce/taxonomy-product_cat.php";

            // Fall back to product archive
            $templates[] = "woocommerce/archive-product.php";
        }
        // Product Tag Archives
        elseif (function_exists('is_product_tag') && is_product_tag()) {
            $term = $this->queriedObject();

            if ($term && isset($term->slug)) {
                $templates[] = "woocommerce/taxonomy-product_tag-{$term->slug}.php";
            }

            $templates[] = "woocommerce/taxonomy-product_tag.php";
            $templates[] = "woocommerce/archive-product.php";
        }
        // Other Product Taxonomy Archives
        elseif (function_exists('is_product_taxonomy') && is_product_taxonomy() && !is_product_category() && !is_product_tag()) {
            $term = $this->queriedObject();

            if ($term && isset($term->taxonomy) && isset($term->slug)) {
                $taxonomy = $term->taxonomy;
                $templates[] = "woocommerce/taxonomy-{$taxonomy}-{$term->slug}.php";
                $templates[] = "woocommerce/taxonomy-{$taxonomy}.php";
            }

            $templates[] = "woocommerce/archive-product.php";
        }
        // Shop Page (Products Archive)
        elseif (function_exists('is_shop') && is_shop()) {
            // Shop page might have a custom template
            $shopPageId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
            if ($shopPageId > 0) {
                $shop_template = get_post_meta($shopPageId, '_wp_page_template', true);
                if ($shop_template && $shop_template !== 'default') {
                    $templates[] = $shop_template;
                }

                // Try page-{slug}.php
                $shop_page = get_post($shopPageId);
                if ($shop_page) {
                    $templates[] = "woocommerce/page-{$shop_page->post_name}.php";
                }
            }

            $templates[] = "woocommerce/archive-product.php";
        }
        // Single Product Pages
        elseif (function_exists('is_product') && is_product()) {
            $product = $this->queriedObject();

            if ($product) {
                $productType = function_exists('wc_get_product') ? wc_get_product($product->ID) : null;

                if ($productType && method_exists($productType, 'get_type')) {
                    $productSubtype = $productType->get_type();

                    // Template specific to product slug
                    $templates[] = "woocommerce/single-product-{$product->post_name}.php";

                    // Template specific to product type (simple, variable, etc)
                    $templates[] = "woocommerce/single-product-{$productSubtype}.php";
                }

                // Custom template assigned to the product
                $wc_template = get_post_meta($product->ID, '_wp_page_template', true);
                if ($wc_template && $wc_template !== 'default') {
                    array_unshift($templates, $wc_template);
                }

                // Standard WooCommerce product template
                $templates[] = "woocommerce/single-product.php";
            } else {
                $templates[] = "woocommerce/single-product.php";
            }
        }
        // Cart Page
        elseif (function_exists('is_cart') && is_cart()) {
            $templates[] = "woocommerce/cart.php";
        }
        // Checkout Page
        elseif (function_exists('is_checkout') && is_checkout()) {
            // Check if we're on a specific checkout endpoint
            if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
                $endpoint = WC()->query->get_current_endpoint();
                if ($endpoint) {
                    $templates[] = "woocommerce/checkout-{$endpoint}.php";
                }
            }

            // Thank you page
            if (is_wc_endpoint_url('order-received')) {
                $templates[] = "woocommerce/checkout-thankyou.php";
            }

            // Standard checkout
            $templates[] = "woocommerce/checkout.php";
        }
        // Account Pages
        elseif (function_exists('is_account_page') && is_account_page()) {
            // Check if we're on a specific account endpoint
            if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
                $endpoint = WC()->query->get_current_endpoint();
                if ($endpoint) {
                    $templates[] = "woocommerce/myaccount-{$endpoint}.php";
                }
            }

            // Login form
            if (!is_user_logged_in()) {
                $templates[] = "woocommerce/myaccount-login.php";
            }

            // Standard account
            $templates[] = "woocommerce/myaccount.php";
        }

        // Add templates to hierarchy if any were found
        if (!empty($templates)) {
            $this->addTemplatesToHierarchy($templates);
        }

        // Return true if any templates were added
        return count($this->templateHierarchy) > $initialCount;
    }

    /**
     * Collect WordPress templates based on current request
     */
    private function collectWordPressTemplates(): void
    {
        // Follow WordPress template hierarchy logic
        $tagTemplates = $this->getTagTemplatesOrder();

        // Store which WordPress conditions are satisfied for this request
        $satisfiedConditions = [];

        // First, identify all conditions that are satisfied (don't break early)
        foreach ($tagTemplates as $tag => $templateGetter) {
            if ($this->isConditionSatisfied($tag)) {
                $satisfiedConditions[] = $tag;
            }
        }

        // WooCommerce special cases - ensure appropriate WordPress templates are added
        if (function_exists('is_woocommerce') && is_woocommerce()) {
            if (is_product() && !in_array('is_single', $satisfiedConditions)) {
                $satisfiedConditions[] = 'is_single';
                $satisfiedConditions[] = 'is_singular';
            }

            if (is_shop() && !in_array('is_post_type_archive', $satisfiedConditions)) {
                $satisfiedConditions[] = 'is_post_type_archive';
                $satisfiedConditions[] = 'is_archive';
            }

            if (is_product_category() && !in_array('is_tax', $satisfiedConditions)) {
                $satisfiedConditions[] = 'is_tax';
                $satisfiedConditions[] = 'is_archive';
                // Add category as well for better fallbacks
                $satisfiedConditions[] = 'is_category';
            }

            if (is_product_tag() && !in_array('is_tax', $satisfiedConditions)) {
                $satisfiedConditions[] = 'is_tax';
                $satisfiedConditions[] = 'is_archive';
                // Add tag as well for better fallbacks
                $satisfiedConditions[] = 'is_tag';
            }

            if ((is_cart() || is_checkout() || is_account_page()) && !in_array('is_page', $satisfiedConditions)) {
                $satisfiedConditions[] = 'is_page';
                $satisfiedConditions[] = 'is_singular';
            }
        }

        // Now add templates for each satisfied condition
        foreach ($satisfiedConditions as $tag) {
            $type = $this->conditionToType($tag);
            $templates = $this->getTemplatesForType($type);

            if (!empty($templates)) {
                $this->addTemplatesToHierarchy($templates);
            }
        }
    }

    /**
     * Get the template loading order as defined in template-loader.php
     *
     * @return array<string, string> Mapping of conditional tags to template getter functions
     */
    private function getTagTemplatesOrder(): array
    {
        // This matches the $tag_templates array in template-loader.php
        return [
            'is_embed'             => 'get_embed_template',
            'is_404'               => 'get_404_template',
            'is_search'            => 'get_search_template',
            'is_front_page'        => 'get_front_page_template',
            'is_home'              => 'get_home_template',
            'is_privacy_policy'    => 'get_privacy_policy_template',
            'is_post_type_archive' => 'get_post_type_archive_template',
            'is_tax'               => 'get_taxonomy_template',
            'is_attachment'        => 'get_attachment_template',
            'is_single'            => 'get_single_template',
            'is_page'              => 'get_page_template',
            'is_singular'          => 'get_singular_template',
            'is_category'          => 'get_category_template',
            'is_tag'               => 'get_tag_template',
            'is_author'            => 'get_author_template',
            'is_date'              => 'get_date_template',
            'is_archive'           => 'get_archive_template',
        ];
    }

    /**
     * Compute hierarchy based on WordPress core conditions
     */
    private function computeWordPressHierarchy(): void
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
    }

    /**
     * Get templates for a plugin-specific template type
     *
     * @param string $type Template type
     * @param string $pluginName Plugin name
     * @return string[] Array of templates
     */
    private function getPluginTemplatesForType(string $type, string $pluginName): array
    {
        // Default templates (will be overridden by specific types)
        $templates = [];

        // Base plugin directory where templates are stored (lowercase)
        $pluginDir = strtolower($pluginName);

        // Handle specific plugin types
        if ($pluginName === 'woocommerce') {
            $templates = $this->getWooCommerceTemplates($type);
        } else {
            // Generic plugin template pattern
            // Add base template for the type
            $templates[] = "{$pluginDir}/{$type}.php";

            // Use the queried object to add more specific templates
            $queriedObject = $this->queriedObject();
            if ($queriedObject) {
                // If it's a post type
                if (isset($queriedObject->post_type)) {
                    $templates[] = "{$pluginDir}/{$type}-{$queriedObject->post_type}.php";
                    if (isset($queriedObject->post_name)) {
                        $templates[] = "{$pluginDir}/{$type}-{$queriedObject->post_type}-{$queriedObject->post_name}.php";
                    }
                }

                // If it's a taxonomy term
                if (isset($queriedObject->taxonomy) && isset($queriedObject->slug)) {
                    $templates[] = "{$pluginDir}/{$type}-{$queriedObject->taxonomy}.php";
                    $templates[] = "{$pluginDir}/{$type}-{$queriedObject->taxonomy}-{$queriedObject->slug}.php";
                }
            }
        }

        // Allow plugins to filter their own templates
        return apply_filters("pollora/template_hierarchy/{$pluginDir}/{$type}_templates", $templates, $this->queriedObject());
    }

    /**
     * Get templates specific to WooCommerce
     *
     * @param string $type Template type
     * @return string[] Array of templates
     */
    private function getWooCommerceTemplates(string $type): array
    {
        $templates = [];
        $queriedObject = $this->queriedObject();

        switch ($type) {
            case 'product':
                if ($queriedObject) {
                    $productType = function_exists('wc_get_product') ? wc_get_product($queriedObject->ID) : null;

                    if ($productType && method_exists($productType, 'get_type')) {
                        $productSubtype = $productType->get_type();

                        // Add template specific to product slug
                        $templates[] = "woocommerce/single-product-{$queriedObject->post_name}.php";

                        // Add template specific to product type (simple, variable, etc)
                        $templates[] = "woocommerce/single-product-{$productSubtype}.php";
                    }

                    // Add standard WooCommerce product template
                    $templates[] = "woocommerce/single-product.php";

                    // Check if there's a custom template assigned in WooCommerce product data
                    $wc_template = get_post_meta($queriedObject->ID, '_wp_page_template', true);
                    if ($wc_template && $wc_template !== 'default') {
                        array_unshift($templates, $wc_template);
                    }
                } else {
                    $templates[] = "woocommerce/single-product.php";
                }
                break;

            case 'product_category':
                if ($queriedObject && isset($queriedObject->slug)) {
                    // Try parent category templates if available
                    if (isset($queriedObject->parent) && $queriedObject->parent) {
                        $parent = get_term($queriedObject->parent, 'product_cat');
                        if ($parent && !is_wp_error($parent)) {
                            $templates[] = "woocommerce/taxonomy-product_cat-{$parent->slug}.php";
                        }
                    }

                    // Current category template
                    $templates[] = "woocommerce/taxonomy-product_cat-{$queriedObject->slug}.php";
                }

                // Generic category template
                $templates[] = "woocommerce/taxonomy-product_cat.php";

                // Fall back to product archive
                $templates[] = "woocommerce/archive-product.php";
                break;

            case 'product_tag':
                if ($queriedObject && isset($queriedObject->slug)) {
                    $templates[] = "woocommerce/taxonomy-product_tag-{$queriedObject->slug}.php";
                }

                // Generic tag template
                $templates[] = "woocommerce/taxonomy-product_tag.php";

                // Fall back to product archive
                $templates[] = "woocommerce/archive-product.php";
                break;

            case 'shop':
                // Shop page might have a custom template
                $shopPageId = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
                if ($shopPageId > 0) {
                    $shop_template = get_post_meta($shopPageId, '_wp_page_template', true);
                    if ($shop_template && $shop_template !== 'default') {
                        $templates[] = $shop_template;
                    }

                    // Try page-{slug}.php
                    $shop_page = get_post($shopPageId);
                    if ($shop_page) {
                        $templates[] = "page-{$shop_page->post_name}.php";
                    }
                }

                // Standard shop template
                $templates[] = "woocommerce/archive-product.php";
                break;

            case 'cart':
                $templates[] = "woocommerce/cart.php";
                break;

            case 'checkout':
                // Check if we're on a specific checkout endpoint
                if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
                    $endpoint = WC()->query->get_current_endpoint();
                    if ($endpoint) {
                        $templates[] = "woocommerce/checkout-{$endpoint}.php";
                    }
                }

                // Standard checkout
                $templates[] = "woocommerce/checkout.php";
                break;

            case 'account':
                // Check if we're on a specific account endpoint
                if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
                    $endpoint = WC()->query->get_current_endpoint();
                    if ($endpoint) {
                        $templates[] = "woocommerce/myaccount-{$endpoint}.php";
                    }
                }

                // Standard account
                $templates[] = "woocommerce/myaccount.php";
                break;

            case 'wc_endpoint':
                // Handle WooCommerce endpoints
                if (function_exists('WC') && isset(WC()->query)) {
                    $endpoint = WC()->query->get_current_endpoint();
                    if ($endpoint) {
                        $templates[] = "woocommerce/endpoint-{$endpoint}.php";

                        // If it's a checkout or account endpoint, add specific fallbacks
                        if (is_checkout()) {
                            $templates[] = "woocommerce/checkout-{$endpoint}.php";
                            $templates[] = "woocommerce/checkout.php";
                        } elseif (is_account_page()) {
                            $templates[] = "woocommerce/myaccount-{$endpoint}.php";
                            $templates[] = "woocommerce/myaccount.php";
                        }
                    }
                }

                // Generic endpoint template
                $templates[] = "woocommerce/endpoint.php";
                break;

            default:
                // Generic WooCommerce template
                $templates[] = "woocommerce/{$type}.php";
                break;
        }

        return $templates;
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
     * Get plugin conditions from config
     *
     * @return array Plugin conditions from config
     */
    private function getPluginConditions(): array
    {
        // Use cached plugin conditions if available
        if (self::$cachedPluginConditions !== null) {
            return self::$cachedPluginConditions;
        }

        // Retrieve the plugin conditions from the Laravel config
        $pluginConditions = config('wordpress.plugin_conditions', []);

        // Allow plugins to register additional plugin conditions
        $pluginConditions = apply_filters('pollora/template_hierarchy/plugin_conditions', $pluginConditions);

        // Cache the result
        self::$cachedPluginConditions = $pluginConditions;

        return $pluginConditions;
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
