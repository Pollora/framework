<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * WordPress bindings middleware
 * 
 * Injects WordPress objects and data into the request for
 * WordPress routes to access.
 */
final class WordPressBindings
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Bind WordPress globals to the request
        $this->bindWordPressGlobals($request);
        
        // Bind WordPress query data
        $this->bindWordPressQueryData($request);
        
        // Bind WordPress post data
        $this->bindWordPressPostData($request);
        
        return $next($request);
    }

    /**
     * Bind WordPress global variables to the request
     */
    private function bindWordPressGlobals(Request $request): void
    {
        global $wp_query, $post, $wp, $wpdb;

        // Bind WordPress globals as request attributes
        if (isset($wp_query)) {
            $request->attributes->set('wp_query', $wp_query);
        }
        
        if (isset($post)) {
            $request->attributes->set('wp_post', $post);
        }
        
        if (isset($wp)) {
            $request->attributes->set('wp', $wp);
        }
        
        if (isset($wpdb)) {
            $request->attributes->set('wpdb', $wpdb);
        }
    }

    /**
     * Bind WordPress query data to the request
     */
    private function bindWordPressQueryData(Request $request): void
    {
        global $wp_query;

        if (!isset($wp_query) || !is_object($wp_query)) {
            return;
        }

        // Bind query variables
        if (method_exists($wp_query, 'get_query_var')) {
            $queryVars = [
                'page_id' => $wp_query->get_query_var('page_id'),
                'p' => $wp_query->get_query_var('p'),
                'cat' => $wp_query->get_query_var('cat'),
                'tag' => $wp_query->get_query_var('tag'),
                'author' => $wp_query->get_query_var('author'),
                'year' => $wp_query->get_query_var('year'),
                'month' => $wp_query->get_query_var('month'),
                'day' => $wp_query->get_query_var('day'),
                's' => $wp_query->get_query_var('s'),
                'paged' => $wp_query->get_query_var('paged'),
            ];
            
            $request->attributes->set('wp_query_vars', array_filter($queryVars));
        }

        // Bind queried object
        if (method_exists($wp_query, 'get_queried_object')) {
            $queriedObject = $wp_query->get_queried_object();
            if ($queriedObject) {
                $request->attributes->set('queried_object', $queriedObject);
            }
        }

        // Bind queried object ID
        if (method_exists($wp_query, 'get_queried_object_id')) {
            $queriedObjectId = $wp_query->get_queried_object_id();
            if ($queriedObjectId) {
                $request->attributes->set('queried_object_id', $queriedObjectId);
            }
        }
    }

    /**
     * Bind WordPress post data to the request
     */
    private function bindWordPressPostData(Request $request): void
    {
        global $post;

        if (!isset($post) || !is_object($post)) {
            return;
        }

        // Bind post data
        $postData = [
            'ID' => $post->ID ?? null,
            'post_title' => $post->post_title ?? null,
            'post_content' => $post->post_content ?? null,
            'post_excerpt' => $post->post_excerpt ?? null,
            'post_name' => $post->post_name ?? null,
            'post_type' => $post->post_type ?? null,
            'post_status' => $post->post_status ?? null,
            'post_date' => $post->post_date ?? null,
            'post_modified' => $post->post_modified ?? null,
            'post_author' => $post->post_author ?? null,
            'post_parent' => $post->post_parent ?? null,
            'menu_order' => $post->menu_order ?? null,
        ];

        $request->attributes->set('wp_post_data', array_filter($postData));

        // Bind post meta if functions are available
        if (function_exists('get_post_meta') && isset($post->ID)) {
            $postMeta = get_post_meta($post->ID);
            $request->attributes->set('wp_post_meta', $postMeta);
        }

        // Bind post terms if functions are available
        if (function_exists('wp_get_post_terms') && isset($post->ID)) {
            $taxonomies = get_object_taxonomies($post->post_type);
            $postTerms = [];
            
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if (!is_wp_error($terms)) {
                    $postTerms[$taxonomy] = $terms;
                }
            }
            
            $request->attributes->set('wp_post_terms', $postTerms);
        }
    }
}