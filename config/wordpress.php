<?php

declare(strict_types=1);

return [
    /**
     * WordPress route conditions.
     *
     * Maps WordPress conditional functions to their route aliases.
     * Key = WordPress function name, Value = alias(es) for routing.
     * Multiple aliases can be defined as an array.
     * These conditions are used by Route::wordpress() and Route::wp() methods.
     */
    'conditions' => [
        // Error and special pages
        'is_404' => ['404', 'not_found'],
        'is_search' => 'search',
        'is_paged' => 'paged',

        // Homepage and blog index
        'is_front_page' => ['/', 'front'],
        'is_home' => ['home', 'blog'],

        // Specific template
        'is_page_template' => 'template',

        // Custom post type hierarchy
        'is_singular' => 'singular',
        'is_single' => 'single',
        'is_attachment' => 'attachment',
        'is_post_type_archive' => ['post-type-archive', 'postTypeArchive'],
        'is_archive' => 'archive',

        // Taxonomies
        'is_category' => ['category', 'cat'],
        'is_tag' => 'tag',
        'is_tax' => 'tax',

        // Time hierarchy
        'is_date' => 'date',
        'is_year' => 'year',
        'is_month' => 'month',
        'is_day' => 'day',
        'is_time' => 'time',

        // Others conditions
        'is_author' => 'author',
        'is_sticky' => 'sticky',
        'is_subpage' => ['subpage', 'subpageof'],
    ],

    /**
     * Mail handling configuration.
     *
     * Controls whether Pollora framework should handle WordPress mail functionality.
     * When enabled (true), Pollora overrides the wp_mail function to use Laravel's mail system.
     * When disabled (false), WordPress will use its native mail handling.
     */
    'enable_mail_handling' => true,

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
        ],
    ],

    'constants' => [
        // WordPress authentication keys and salts
        'auth_key' => env('AUTH_KEY'),
        'secure_auth_key' => env('SECURE_AUTH_KEY'),
        'logged_in_key' => env('LOGGED_IN_KEY'),
        'nonce_key' => env('NONCE_KEY'),
        'auth_salt' => env('AUTH_SALT'),
        'secure_auth_salt' => env('SECURE_AUTH_SALT'),
        'logged_in_salt' => env('LOGGED_IN_SALT'),
        'nonce_salt' => env('NONCE_SALT'),
    ],
];
