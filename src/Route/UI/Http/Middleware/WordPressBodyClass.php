<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * WordPress body class middleware
 * 
 * Adds WordPress body classes to the request for use in templates.
 * These classes help maintain WordPress theme compatibility.
 */
final class WordPressBodyClass
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Generate and bind WordPress body classes
        $this->bindWordPressBodyClasses($request);
        
        return $next($request);
    }

    /**
     * Generate and bind WordPress body classes to the request
     */
    private function bindWordPressBodyClasses(Request $request): void
    {
        $bodyClasses = $this->generateBodyClasses($request);
        
        // Bind to request attributes for template access
        $request->attributes->set('wp_body_class', implode(' ', $bodyClasses));
        $request->attributes->set('wp_body_classes', $bodyClasses);
        
        // Share with views if Laravel view system is available
        if (function_exists('view')) {
            view()->share('wp_body_class', implode(' ', $bodyClasses));
            view()->share('wp_body_classes', $bodyClasses);
        }
    }

    /**
     * Generate WordPress body classes
     */
    private function generateBodyClasses(Request $request): array
    {
        $classes = [];

        // Base WordPress classes
        $classes[] = 'wp-route';
        
        // Add conditional classes based on WordPress state
        $classes = array_merge($classes, $this->getConditionalClasses());
        
        // Add post-specific classes
        $classes = array_merge($classes, $this->getPostClasses());
        
        // Add user-specific classes
        $classes = array_merge($classes, $this->getUserClasses());
        
        // Add theme-specific classes
        $classes = array_merge($classes, $this->getThemeClasses());
        
        // Add custom classes from WordPress filters
        $classes = array_merge($classes, $this->getCustomClasses($classes, $request));
        
        // Clean and return unique classes
        return array_unique(array_filter(array_map('trim', $classes)));
    }

    /**
     * Get conditional classes based on WordPress conditional tags
     */
    private function getConditionalClasses(): array
    {
        $classes = [];

        // Check WordPress conditional functions
        $conditionals = [
            'is_home' => 'home',
            'is_front_page' => 'front-page',
            'is_blog' => 'blog',
            'is_archive' => 'archive',
            'is_date' => 'date',
            'is_year' => 'year',
            'is_month' => 'month',
            'is_day' => 'day',
            'is_time' => 'time',
            'is_author' => 'author',
            'is_category' => 'category',
            'is_tag' => 'tag',
            'is_tax' => 'tax',
            'is_search' => 'search',
            'is_404' => 'error404',
            'is_paged' => 'paged',
            'is_attachment' => 'attachment',
            'is_single' => 'single',
            'is_page' => 'page',
            'is_singular' => 'singular',
            'is_preview' => 'preview',
            'is_admin' => 'admin',
            'is_feed' => 'feed',
            'is_trackback' => 'trackback',
            'is_robots' => 'robots',
        ];

        foreach ($conditionals as $function => $class) {
            if (function_exists($function) && $function()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Get post-specific classes
     */
    private function getPostClasses(): array
    {
        global $post;
        $classes = [];

        if (!isset($post) || !is_object($post)) {
            return $classes;
        }

        // Post ID class
        if (isset($post->ID)) {
            $classes[] = "postid-{$post->ID}";
        }

        // Post type class
        if (isset($post->post_type)) {
            $classes[] = "single-{$post->post_type}";
            $classes[] = "type-{$post->post_type}";
        }

        // Post name/slug class
        if (isset($post->post_name)) {
            $classes[] = "single-{$post->post_type}-{$post->post_name}";
        }

        // Post format classes
        if (function_exists('get_post_format')) {
            $format = get_post_format($post->ID);
            if ($format) {
                $classes[] = "single-format-{$format}";
            } else {
                $classes[] = 'single-format-standard';
            }
        }

        // Post status class
        if (isset($post->post_status)) {
            $classes[] = "status-{$post->post_status}";
        }

        // Post author class
        if (isset($post->post_author)) {
            $classes[] = "author-{$post->post_author}";
            
            if (function_exists('get_userdata')) {
                $author = get_userdata($post->post_author);
                if ($author) {
                    $classes[] = "author-{$author->user_nicename}";
                }
            }
        }

        // Sticky post class
        if (function_exists('is_sticky') && is_sticky($post->ID)) {
            $classes[] = 'sticky';
        }

        // Post thumbnail class
        if (function_exists('has_post_thumbnail') && has_post_thumbnail($post->ID)) {
            $classes[] = 'has-post-thumbnail';
        }

        return $classes;
    }

    /**
     * Get user-specific classes
     */
    private function getUserClasses(): array
    {
        $classes = [];

        // Logged in/out classes
        if (function_exists('is_user_logged_in')) {
            if (is_user_logged_in()) {
                $classes[] = 'logged-in';
                
                // Current user classes
                if (function_exists('wp_get_current_user')) {
                    $currentUser = wp_get_current_user();
                    if ($currentUser && $currentUser->ID) {
                        $classes[] = "user-{$currentUser->ID}";
                        $classes[] = "user-{$currentUser->user_login}";
                        
                        // User role classes
                        if (!empty($currentUser->roles)) {
                            foreach ($currentUser->roles as $role) {
                                $classes[] = "role-{$role}";
                            }
                        }
                    }
                }
            } else {
                $classes[] = 'logged-out';
            }
        }

        return $classes;
    }

    /**
     * Get theme-specific classes
     */
    private function getThemeClasses(): array
    {
        $classes = [];

        // Active theme class
        if (function_exists('get_template')) {
            $template = get_template();
            $classes[] = "theme-{$template}";
        }

        // Child theme class
        if (function_exists('get_stylesheet')) {
            $stylesheet = get_stylesheet();
            if ($stylesheet !== get_template()) {
                $classes[] = "child-theme-{$stylesheet}";
            }
        }

        // Theme support classes
        if (function_exists('current_theme_supports')) {
            $supports = [
                'post-thumbnails' => 'post-thumbnails',
                'custom-header' => 'custom-header',
                'custom-background' => 'custom-background',
                'menus' => 'menus',
                'widgets' => 'widgets',
                'html5' => 'html5',
                'title-tag' => 'title-tag',
                'custom-logo' => 'custom-logo',
            ];

            foreach ($supports as $feature => $class) {
                if (current_theme_supports($feature)) {
                    $classes[] = "supports-{$class}";
                }
            }
        }

        return $classes;
    }

    /**
     * Get custom classes from WordPress filters
     */
    private function getCustomClasses(array $existingClasses, Request $request): array
    {
        if (!function_exists('apply_filters')) {
            return [];
        }

        // Apply WordPress body_class filter
        $customClasses = apply_filters('body_class', [], get_body_class());
        
        // Apply Pollora-specific filter
        $customClasses = apply_filters('pollora_body_class', $customClasses, $request);

        return is_array($customClasses) ? $customClasses : [];
    }
}