<?php
/**
 * Plugin Name: %plugin_name%
 * Plugin URI: %plugin_uri%
 * Description: %plugin_description%
 * Version: %plugin_version%
 * Author: %plugin_author%
 * Author URI: %plugin_author_uri%
 * Text Domain: %plugin_slug%
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 8.1
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('%PLUGIN_NAME%_VERSION', '%plugin_version%');
define('%PLUGIN_NAME%_PLUGIN_FILE', __FILE__);
define('%PLUGIN_NAME%_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('%PLUGIN_NAME%_PLUGIN_URL', plugin_dir_url(__FILE__));

// Register the plugin with Pollora framework
if (class_exists('Pollora\\Plugin\\Application\\Services\\PluginRegistrar')) {
    $registrar = app('Pollora\\Plugin\\Application\\Services\\PluginRegistrar');
    $registrar->register('%plugin_name%', __DIR__);
}

/**
 * Initialize the plugin.
 */
function %plugin_function_name%_init(): void
{
    // Load plugin textdomain for translations
    load_plugin_textdomain(
        '%plugin_slug%',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Initialize plugin functionality
    if (class_exists('Plugin\\%plugin_namespace%\\%plugin_namespace%Plugin')) {
        $plugin = new Plugin\%plugin_namespace%\%plugin_namespace%Plugin();

        // Register activation/deactivation hooks through the plugin class
        register_activation_hook(__FILE__, [$plugin, 'activate']);
        register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);
        register_uninstall_hook(__FILE__, [Plugin\%plugin_namespace%\%plugin_namespace%Plugin::class, 'uninstall']);
    }
}
add_action('plugins_loaded', '%plugin_function_name%_init');

/**
 * Admin notices for missing dependencies.
 */
function %plugin_function_name%_admin_notices(): void
{
    if (! class_exists('Pollora\\Plugin\\Application\\Services\\PluginManager')) {
        echo view('admin-notice');
    }
}
add_action('admin_notices', '%plugin_function_name%_admin_notices');
