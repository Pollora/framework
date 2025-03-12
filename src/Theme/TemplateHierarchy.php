<?php

declare(strict_types=1);

namespace Pollora\Theme;

/**
 * Class TemplateHierarchy
 *
 * Récupère la hiérarchie des templates WordPress avant le chargement de la page.
 */
class TemplateHierarchy
{
    /**
     * Store the template hierarchy
     *
     * @var array
     */
    private $templateHierarchy = [];

    /**
     * Cache for the queried object
     *
     * @var object|null
     */
    private $queriedObject = null;

    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        // Empty constructor
    }

    /**
     * Get the queried object (with caching)
     *
     * @return object|null
     */
    private function queriedObject()
    {
        if ($this->queriedObject === null) {
            $this->queriedObject = get_queried_object();
        }

        return $this->queriedObject;
    }

    /**
     * Get the template hierarchy for the current request
     *
     * @return array The template hierarchy
     */
    public function hierarchy()
    {
        // Only compute hierarchy if not already done
        if (empty($this->templateHierarchy)) {
            $this->computeHierarchy();
        }

        return $this->templateHierarchy;
    }

    /**
     * Generate template hierarchy based on current request
     *
     * @return void
     */
    private function computeHierarchy()
    {
        $templateTypes = $this->templateTypes();

        foreach ($templateTypes as $type => $conditional) {
            if (function_exists($conditional) && call_user_func($conditional)) {
                $templates = $this->templateForType($type);

                if (! empty($templates)) {
                    // Add templates with .blade.php extension first
                    $this->addBladeTemplateVariants($templates);

                    // Then add regular templates
                    foreach ($templates as $template) {
                        $this->templateHierarchy[] = $template;
                    }

                    // Add block template variants if using a block theme
                    if (wp_is_block_theme()) {
                        $this->addBlockTemplateVariants($templates);
                    }
                }
            }
        }

        // Make sure hierarchy is unique
        $this->templateHierarchy = array_unique($this->templateHierarchy);
    }

    /**
     * Add Blade template variants to the hierarchy
     *
     * @param  array  $templates  Regular PHP templates
     * @return void
     */
    private function addBladeTemplateVariants($templates)
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
     * @param  array  $templates  Regular PHP templates
     * @return void
     */
    private function addBlockTemplateVariants($templates)
    {
        if (! function_exists('get_block_theme_folders')) {
            return;
        }

        $blockFolders = get_block_theme_folders();

        foreach ($templates as $template) {
            if (str_ends_with($template, '.php')) {
                $htmlTemplate = $blockFolders['wp_template'].'/'.str_replace('.php', '.html', $template);
                $this->templateHierarchy[] = $htmlTemplate;
            } else {
                // Block theme custom template (no suffix)
                $this->templateHierarchy[] = $blockFolders['wp_template'].'/'.$template.'.html';
            }
        }
    }

    /**
     * Get templates for a specific template type
     *
     * @param  string  $type  Template type
     * @return array Array of templates
     */
    private function templateForType($type)
    {
        $templates = [];

        switch ($type) {
            case 'single':
                $templates = $this->singleTemplates();
                break;

            case 'page':
                $templates = $this->pageTemplates();
                break;

            case 'category':
                $templates = $this->categoryTemplates();
                break;

            case 'tag':
                $templates = $this->tagTemplates();
                break;

            case 'taxonomy':
                $templates = $this->taxonomyTemplates();
                break;

            case 'archive':
                $templates = $this->archiveTemplates();
                break;

            case 'author':
                $templates = $this->authorTemplates();
                break;

            case 'date':
                $templates = $this->dateTemplates();
                break;

            case 'home':
                $templates = ['home.php', 'index.php'];
                break;

            case 'front_page':
                $templates = ['front-page.php'];
                break;

            case 'singular':
                $templates = ['singular.php'];
                break;

            case '404':
                $templates = ['404.php'];
                break;

            case 'search':
                $templates = ['search.php'];
                break;

            case 'embed':
                $templates = ['embed.php'];
                break;

            case 'index':
                $templates = ['index.php'];
                break;
        }

        return $templates;
    }

    /**
     * Get single post templates
     *
     * @return array
     */
    private function singleTemplates()
    {
        $templates = [];
        $post = $this->queriedObject();

        if (! $post) {
            return ['single.php'];
        }

        $templates[] = "single-{$post->post_type}-{$post->post_name}.php";
        $templates[] = "single-{$post->post_type}.php";
        $templates[] = 'single.php';

        return $templates;
    }

    /**
     * Get page templates
     *
     * @return array
     */
    private function pageTemplates()
    {
        $templates = [];
        $page = $this->queriedObject();

        if (! $page) {
            return ['page.php'];
        }

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
     * @return array
     */
    private function categoryTemplates()
    {
        $templates = [];
        $category = $this->queriedObject();

        if (! $category) {
            return ['category.php', 'archive.php'];
        }

        $templates[] = "category-{$category->slug}.php";
        $templates[] = "category-{$category->term_id}.php";
        $templates[] = 'category.php';
        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get tag templates
     *
     * @return array
     */
    private function tagTemplates()
    {
        $templates = [];
        $tag = $this->queriedObject();

        if (! $tag) {
            return ['tag.php', 'archive.php'];
        }

        $templates[] = "tag-{$tag->slug}.php";
        $templates[] = "tag-{$tag->term_id}.php";
        $templates[] = 'tag.php';
        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get taxonomy templates
     *
     * @return array
     */
    private function taxonomyTemplates()
    {
        $templates = [];
        $term = $this->queriedObject();

        if (! $term || ! isset($term->taxonomy)) {
            return ['taxonomy.php', 'archive.php'];
        }

        $taxonomy = $term->taxonomy;

        $templates[] = "taxonomy-$taxonomy-{$term->slug}.php";
        $templates[] = "taxonomy-$taxonomy.php";
        $templates[] = 'taxonomy.php';
        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get archive templates
     *
     * @return array
     */
    private function archiveTemplates()
    {
        $templates = [];
        $postType = get_query_var('post_type');

        if ($postType) {
            $templates[] = "archive-{$postType}.php";
        }

        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get author templates
     *
     * @return array
     */
    private function authorTemplates()
    {
        $templates = [];
        $author = $this->queriedObject();

        if (! $author) {
            return ['author.php', 'archive.php'];
        }

        $templates[] = "author-{$author->user_nicename}.php";
        $templates[] = "author-{$author->ID}.php";
        $templates[] = 'author.php';
        $templates[] = 'archive.php';

        return $templates;
    }

    /**
     * Get date templates
     *
     * @return array
     */
    private function dateTemplates()
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
     * Get template types and their conditional functions
     *
     * @return array Template types with their conditional functions
     */
    private function templateTypes()
    {
        return [
            'single' => 'is_single',
            'page' => 'is_page',
            'singular' => 'is_singular',
            'category' => 'is_category',
            'tag' => 'is_tag',
            'taxonomy' => 'is_tax',
            'author' => 'is_author',
            'date' => 'is_date',
            'archive' => 'is_post_type_archive',
            'home' => 'is_home',
            'front_page' => 'is_front_page',
            '404' => 'is_404',
            'search' => 'is_search',
            'embed' => 'is_embed',
            'index' => '__return_true',
        ];
    }
}
