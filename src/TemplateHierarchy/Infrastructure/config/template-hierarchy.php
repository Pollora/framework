<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WordPress Template Conditions
    |--------------------------------------------------------------------------
    |
    | This configuration defines the WordPress conditional functions and their
    | template types. These are used to determine which templates should be loaded
    | for different types of WordPress pages.
    |
    */
    'conditions' => [
        'is_embed' => 'embed',
        'is_404' => '404',
        'is_search' => 'search',
        'is_front_page' => 'front_page',
        'is_home' => 'home',
        'is_privacy_policy' => 'privacy_policy',
        'is_post_type_archive' => 'post_type_archive',
        'is_tax' => 'taxonomy',
        'is_attachment' => 'attachment',
        'is_single' => 'single',
        'is_page' => 'page',
        'is_singular' => 'singular',
        'is_category' => 'category',
        'is_tag' => 'tag',
        'is_author' => 'author',
        'is_date' => 'date',
        'is_archive' => 'archive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Template Paths
    |--------------------------------------------------------------------------
    |
    | Additional template paths from plugins. These are used to look for
    | templates in plugin directories.
    |
    */
    'plugin_template_paths' => [
        // WooCommerce templates
        WP_PLUGIN_DIR.'/woocommerce/templates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade View Namespace
    |--------------------------------------------------------------------------
    |
    | The Blade view namespace used for templates.
    |
    */
    'blade_namespace' => 'theme',
];
