<?php

declare(strict_types=1);

return [
    /**
     * WordPress route conditions.
     *
     * Maps WordPress conditional tags to route URIs.
     * These conditions are used by Route::wordpress() and Route::wp() methods.
     */
    'conditions' => [
        // Core WordPress conditions
        'is_404' => '404',
        'is_archive' => 'archive',
        'is_attachment' => 'attachment',
        'is_author' => 'author',
        'is_category' => ['category', 'cat'],
        'is_date' => 'date',
        'is_day' => 'day',
        'is_front_page' => ['/', 'front'],
        'is_home' => ['home', 'blog'],
        'is_month' => 'month',
        'is_page_template' => 'template',
        'is_page' => 'page',
        'is_paged' => 'paged',
        'is_post_type_archive' => ['post-type-archive', 'postTypeArchive'],
        'is_search' => 'search',
        'is_single' => 'single',
        'is_singular' => 'singular',
        'is_sticky' => 'sticky',
        'is_subpage' => ['subpage', 'subpageof'],
        'is_tag' => 'tag',
        'is_tax' => 'tax',
        'is_time' => 'time',
        'is_year' => 'year',
    ],

    'plugin_conditions' => [
        'woocommerce' => [
            // WooCommerce conditions
            'is_shop' => 'shop',
            'is_product' => 'product',
            'is_cart' => 'cart',
            'is_checkout' => 'checkout',
            'is_account_page' => 'account',
            'is_product_category' => 'product_category',
            'is_product_tag' => 'product_tag',
            'is_wc_endpoint_url' => 'wc_endpoint',
        ]
    ],

    // WordPress authentication keys and salts
    'auth_key' => env('AUTH_KEY'),
    'secure_auth_key' => env('SECURE_AUTH_KEY'),
    'logged_in_key' => env('LOGGED_IN_KEY'),
    'nonce_key' => env('NONCE_KEY'),
    'auth_salt' => env('AUTH_SALT'),
    'secure_auth_salt' => env('SECURE_AUTH_SALT'),
    'logged_in_salt' => env('LOGGED_IN_SALT'),
    'nonce_salt' => env('NONCE_SALT'),

    // WordPress multisite configuration
    'wp_allow_multisite' => env('WP_ALLOW_MULTISITE'),
    'multisite' => env('MULTISITE'),
    'subdomain_install' => env('SUBDOMAIN_INSTALL'),
    'domain_current_site' => env('DOMAIN_CURRENT_SITE'),
    'path_current_site' => env('PATH_CURRENT_SITE'),
    'site_id_current_site' => env('SITE_ID_CURRENT_SITE'),
    'blog_id_current_site' => env('BLOG_ID_CURRENT_SITE'),

    // Database caching
    'caching' => env('DB_CACHE'),
];
