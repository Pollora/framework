<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * Frontend controller for WordPress template fallback.
 *
 * This controller handles requests that don't match any defined routes
 * by falling back to WordPress template hierarchy. Special WordPress requests
 * (robots.txt, favicon, feeds, trackbacks) are handled earlier in the WordPress
 * bootstrap process and will not reach this controller.
 *
 * The controller respects wp_using_themes() condition and implements the same
 * template hierarchy logic as WordPress's template-loader.php but using
 * Laravel's View system instead of PHP includes.
 */
class FrontendController
{
    /**
     * Handle the request using WordPress template hierarchy.
     */
    public function handle(Request $request): Response
    {
        // Early return if themes are not being used
        if (function_exists('wp_using_themes') && ! wp_using_themes()) {
            abort(404, 'Themes are disabled');
        }

        // Build the most specific template slug possible
        $slug = $this->buildTemplateSlug();

        // Get template hierarchy using WordPress filters (like Sage does)
        $templates = $this->getTemplateHierarchy($slug);

        // Find the first template that exists
        foreach ($templates as $template) {
            if (View::exists($template)) {
                return response(View::make($template));
            }
        }

        // If no template found, return 404
        abort(404);
    }

    /**
     * Build the most specific template slug based on WordPress context.
     */
    protected function buildTemplateSlug(): string
    {
        // Single post
        if (function_exists('is_single') && is_single()) {
            if (function_exists('get_post')) {
                $post = get_post();
                if ($post) {
                    return "single-{$post->post_type}-{$post->post_name}";
                }
            }

            return 'single';
        }

        // Page
        if (function_exists('is_page') && is_page()) {
            if (function_exists('get_post')) {
                $post = get_post();
                if ($post) {
                    return "page-{$post->post_name}";
                }
            }

            return 'page';
        }

        // Category archive
        if (function_exists('is_category') && is_category()) {
            $term = get_queried_object();
            if ($term) {
                return "category-{$term->slug}";
            }

            return 'category';
        }

        // Tag archive
        if (function_exists('is_tag') && is_tag()) {
            $term = get_queried_object();
            if ($term) {
                return "tag-{$term->slug}";
            }

            return 'tag';
        }

        // Custom taxonomy
        if (function_exists('is_tax') && is_tax()) {
            $term = get_queried_object();
            if ($term) {
                return "taxonomy-{$term->taxonomy}-{$term->slug}";
            }

            return 'taxonomy';
        }

        // Author archive
        if (function_exists('is_author') && is_author()) {
            $author = get_queried_object();
            if ($author) {
                return "author-{$author->user_nicename}";
            }

            return 'author';
        }

        // Date archive
        if (function_exists('is_date') && is_date()) {
            if (is_day()) {
                return 'date-day';
            }
            if (is_month()) {
                return 'date-month';
            }
            if (is_year()) {
                return 'date-year';
            }

            return 'date';
        }

        // Custom post type archive
        if (function_exists('is_post_type_archive') && is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }

            return "archive-{$post_type}";
        }

        // Search results
        if (function_exists('is_search') && is_search()) {
            return 'search';
        }

        // 404
        if (function_exists('is_404') && is_404()) {
            return '404';
        }

        // Front page
        if (function_exists('is_front_page') && is_front_page()) {
            return 'front-page';
        }

        // Home (blog index)
        if (function_exists('is_home') && is_home()) {
            return 'home';
        }

        // Default fallback
        return 'index';
    }

    /**
     * Get template hierarchy using WordPress filters.
     *
     * This approach is inspired by Sage Acorn and uses WordPress's own
     * template hierarchy system with filters.
     *
     * @return array<string>
     */
    protected function getTemplateHierarchy(string $slug): array
    {
        $templates = [];

        // Build hierarchy based on context
        if (function_exists('is_single') && is_single()) {
            $post = get_post();
            if ($post) {
                $templates = [
                    "single-{$post->post_type}-{$post->post_name}",
                    "single-{$post->post_type}",
                    'single',
                ];
            }
        } elseif (function_exists('is_page') && is_page()) {
            $post = get_post();
            if ($post) {
                $template = get_page_template_slug($post);
                if ($template) {
                    $templates[] = $template;
                }
                $templates = array_merge($templates, [
                    "page-{$post->post_name}",
                    "page-{$post->ID}",
                    'page',
                ]);
            }
        } elseif (function_exists('is_category') && is_category()) {
            $term = get_queried_object();
            if ($term) {
                $templates = [
                    "category-{$term->slug}",
                    "category-{$term->term_id}",
                    'category',
                    'archive',
                ];
            }
        } elseif (function_exists('is_tag') && is_tag()) {
            $term = get_queried_object();
            if ($term) {
                $templates = [
                    "tag-{$term->slug}",
                    "tag-{$term->term_id}",
                    'tag',
                    'archive',
                ];
            }
        } elseif (function_exists('is_tax') && is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $templates = [
                    "taxonomy-{$term->taxonomy}-{$term->slug}",
                    "taxonomy-{$term->taxonomy}",
                    'taxonomy',
                    'archive',
                ];
            }
        } elseif (function_exists('is_author') && is_author()) {
            $author = get_queried_object();
            if ($author) {
                $templates = [
                    "author-{$author->user_nicename}",
                    "author-{$author->ID}",
                    'author',
                    'archive',
                ];
            }
        } elseif (function_exists('is_date') && is_date()) {
            $templates = ['date', 'archive'];
        } elseif (function_exists('is_post_type_archive') && is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            $templates = [
                "archive-{$post_type}",
                'archive',
            ];
        } elseif (function_exists('is_search') && is_search()) {
            $templates = ['search'];
        } elseif (function_exists('is_404') && is_404()) {
            $templates = ['404'];
        } elseif (function_exists('is_front_page') && is_front_page()) {
            $templates = ['front-page', 'home'];
        } elseif (function_exists('is_home') && is_home()) {
            $templates = ['home', 'index'];
        }

        // Always add index as final fallback
        if (! in_array('index', $templates)) {
            $templates[] = 'index';
        }

        // Apply WordPress filters if available
        if (function_exists('apply_filters')) {
            $template_type = $this->getTemplateType();
            $templates = apply_filters("{$template_type}_template_hierarchy", $templates);
        }

        return $templates;
    }

    /**
     * Get the current template type for filters.
     */
    protected function getTemplateType(): string
    {
        if (function_exists('is_single') && is_single()) {
            return 'single';
        }
        if (function_exists('is_page') && is_page()) {
            return 'page';
        }
        if (function_exists('is_category') && is_category()) {
            return 'category';
        }
        if (function_exists('is_tag') && is_tag()) {
            return 'tag';
        }
        if (function_exists('is_tax') && is_tax()) {
            return 'taxonomy';
        }
        if (function_exists('is_author') && is_author()) {
            return 'author';
        }
        if (function_exists('is_date') && is_date()) {
            return 'date';
        }
        if (function_exists('is_post_type_archive') && is_post_type_archive()) {
            return 'archive';
        }
        if (function_exists('is_search') && is_search()) {
            return 'search';
        }
        if (function_exists('is_404') && is_404()) {
            return '404';
        }
        if (function_exists('is_front_page') && is_front_page()) {
            return 'frontpage';
        }
        if (function_exists('is_home') && is_home()) {
            return 'home';
        }

        return 'index';
    }
}
