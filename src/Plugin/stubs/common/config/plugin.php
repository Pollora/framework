<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the %plugin_name% plugin.
    | You can define plugin-specific settings here.
    |
    */

    'name' => '%plugin_name%',
    'version' => '%plugin_version%',
    'slug' => '%plugin_slug%',
    'text_domain' => '%plugin_slug%',

    /*
    |--------------------------------------------------------------------------
    | Admin Menu Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the WordPress admin menu.
    |
    */
    'admin_menu' => [
        'page_title' => '%plugin_name% Settings',
        'menu_title' => '%plugin_name%',
        'capability' => 'manage_options',
        'menu_slug' => '%plugin_slug%-settings',
        'icon' => 'dashicons-admin-plugins',
        'position' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database table names and settings.
    |
    */
    'database' => [
        'tables' => [
            // Define your custom tables here
            // 'example_table' => '%plugin_slug%_example',
        ],
        'options' => [
            // Define your plugin options here
            'settings' => '%plugin_slug%_settings',
            'version' => '%plugin_slug%_version',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin assets using Vite for modern asset management.
    |
    */
    'assets' => [
        'version' => '%plugin_version%',
        'assets_path' => 'resources/assets',

        // Vite asset container configuration
        'asset_container' => [
            'hot_file' => public_path('%plugin_name%.hot'),
            'build_directory' => 'build/plugins/%plugin_name%',
            'manifest_path' => 'manifest.json',
            'base_path' => 'resources/assets/',
            'module_path' => __DIR__ . '/../',
            'module_type' => 'plugin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shortcode Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin shortcodes.
    |
    */
    'shortcodes' => [
        // Define your shortcodes here
        // 'example_shortcode' => [
        //     'callback' => 'handle_example_shortcode',
        //     'attributes' => [
        //         'title' => '',
        //         'content' => '',
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AJAX Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AJAX handlers.
    |
    */
    'ajax' => [
        'actions' => [
            // Define your AJAX actions here
            // 'example_action' => [
            //     'callback' => 'handle_example_action',
            //     'public' => true,
            //     'private' => true,
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for scheduled events.
    |
    */
    'cron' => [
        'events' => [
            // Define your cron events here
            // 'example_daily_task' => [
            //     'hook' => '%plugin_slug%_daily_task',
            //     'recurrence' => 'daily',
            //     'callback' => 'handle_daily_task',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capabilities Configuration
    |--------------------------------------------------------------------------
    |
    | Custom capabilities for the plugin.
    |
    */
    'capabilities' => [
        // Define custom capabilities here
        // 'example_capability' => 'Manage Example Feature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Dependencies
    |--------------------------------------------------------------------------
    |
    | Required plugins and minimum versions.
    |
    */
    'dependencies' => [
        'wordpress' => '5.0',
        'php' => '8.1',
        'plugins' => [
            // Define required plugins here
            // 'woocommerce/woocommerce.php' => '5.0',
        ],
    ],
];
