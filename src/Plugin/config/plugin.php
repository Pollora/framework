<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Path
    |--------------------------------------------------------------------------
    |
    | The path where plugins are located. This should be an absolute path
    | to the directory containing your plugin folders. Each plugin should
    | have its own subdirectory with a main plugin file.
    |
    */
    'path' => env('PLUGIN_PATH', base_path('plugins')),

    /*
    |--------------------------------------------------------------------------
    | Plugin Structure
    |--------------------------------------------------------------------------
    |
    | Define the expected directory structure for plugins. This configuration
    | helps the framework locate and organize plugin assets, views, and other
    | resources.
    |
    */
    'structure' => [
        'assets' => 'assets',
        'views' => 'views',
        'config' => 'config',
        'database' => 'database',
        'routes' => 'routes',
        'languages' => 'languages',
        'includes' => 'includes',
        'admin' => 'admin',
        'public' => 'public',
        'tests' => 'tests',
        'app' => 'app',
        'src' => 'src',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Autoloading
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin autoloading. The framework uses PSR-4 autoloading
    | with fixed namespace conventions: Plugin\{PluginName}\
    |
    */
    'autoload' => [
        'enabled' => true,
        'namespace_prefix' => 'Plugin',
        'preferred_source_dir' => 'app', // Prefer 'app' over 'src'
        'fallback_source_dir' => 'src',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Discovery
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin discovery and scanning. This includes settings
    | for finding plugins, parsing headers, and managing plugin metadata.
    |
    */
    'discovery' => [
        'enabled' => true,
        'cache_enabled' => env('PLUGIN_CACHE_ENABLED', true),
        'scan_on_boot' => env('PLUGIN_SCAN_ON_BOOT', false),
        'required_headers' => [
            'Plugin Name',
            'Version',
        ],
        'optional_headers' => [
            'Description',
            'Author',
            'Plugin URI',
            'Author URI',
            'Text Domain',
            'Domain Path',
            'Requires at least',
            'Tested up to',
            'Requires PHP',
            'Network',
            'License',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Assets
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin asset management including CSS, JavaScript,
    | and other static resources. This includes versioning and optimization
    | settings.
    |
    */
    'assets' => [
        'enabled' => true,
        'version' => env('PLUGIN_ASSETS_VERSION', '1.0.0'),
        'url_prefix' => '/plugins',
        'manifest_file' => 'manifest.json',
        'hot_reload' => env('PLUGIN_HOT_RELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Views
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin view handling including Blade template support,
    | view compilation, and template paths.
    |
    */
    'views' => [
        'enabled' => true,
        'blade_support' => true,
        'cache_compiled' => env('PLUGIN_VIEWS_CACHE', true),
        'auto_register_paths' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for plugin configuration management including automatic
    | loading of plugin config files and namespace resolution.
    |
    */
    'config' => [
        'auto_load' => true,
        'namespace_prefix' => 'plugin',
        'delayed_loading' => [
            'admin-menu.php',
            'settings.php',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Translation
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin internationalization and localization support.
    | This includes language file loading and text domain management.
    |
    */
    'translation' => [
        'enabled' => true,
        'auto_load_text_domains' => true,
        'fallback_locale' => 'en',
        'language_path' => 'languages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Security
    |--------------------------------------------------------------------------
    |
    | Security settings for plugins including file validation, capability
    | checks, and access restrictions.
    |
    */
    'security' => [
        'validate_main_file' => true,
        'check_direct_access_protection' => true,
        'required_capabilities' => [
            'install' => 'install_plugins',
            'activate' => 'activate_plugins',
            'deactivate' => 'activate_plugins',
            'delete' => 'delete_plugins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Dependencies
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin dependency management including required
    | WordPress version, PHP version, and other plugin dependencies.
    |
    */
    'dependencies' => [
        'check_enabled' => true,
        'min_wp_version' => '5.0',
        'min_php_version' => '8.1',
        'dependency_resolver' => 'strict', // 'strict' or 'loose'
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Performance
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings for plugins including caching,
    | lazy loading, and resource optimization.
    |
    */
    'performance' => [
        'cache_plugin_data' => env('PLUGIN_CACHE_DATA', true),
        'lazy_load_plugins' => env('PLUGIN_LAZY_LOAD', false),
        'optimize_autoloader' => env('PLUGIN_OPTIMIZE_AUTOLOADER', true),
        'preload_active_plugins' => env('PLUGIN_PRELOAD_ACTIVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Development
    |--------------------------------------------------------------------------
    |
    | Development-specific settings for plugins including debugging,
    | error reporting, and development tools.
    |
    */
    'development' => [
        'debug_mode' => env('PLUGIN_DEBUG', false),
        'show_errors' => env('PLUGIN_SHOW_ERRORS', false),
        'log_discovery' => env('PLUGIN_LOG_DISCOVERY', false),
        'hot_reload_enabled' => env('PLUGIN_HOT_RELOAD', false),
    ],
];