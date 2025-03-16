<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Installer;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use Pollora\Events\WordPress\Installer\Plugin\PluginActivated;
use Pollora\Events\WordPress\Installer\Plugin\PluginDeactivated;
use Pollora\Events\WordPress\Installer\Plugin\PluginDeleted;
use Pollora\Events\WordPress\Installer\Plugin\PluginInstalled;
use Pollora\Events\WordPress\Installer\Plugin\PluginUpdated;
use Pollora\Events\WordPress\Installer\Theme\ThemeActivated;
use Pollora\Events\WordPress\Installer\Theme\ThemeDeleted;
use Pollora\Events\WordPress\Installer\Theme\ThemeInstalled;
use Pollora\Events\WordPress\Installer\Theme\ThemeUpdated;
use WP_Upgrader;

/**
 * Event dispatcher for WordPress installation-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress installation actions
 * such as plugin/theme installations, activations, deactivations, and WordPress core updates.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class InstallerEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'upgrader_process_complete',
        'activate_plugin',
        'deactivate_plugin',
        'switch_theme',
        'delete_site_transient_update_themes',
        'pre_option_uninstall_plugins',
        'pre_set_site_transient_update_plugins',
    ];

    /**
     * Handle plugin/theme installation or update completion.
     *
     * @param  WP_Upgrader  $upgrader  Upgrader instance
     * @param  array  $extra  Extra data about the upgrade
     */
    public function handleUpgraderProcessComplete(WP_Upgrader $upgrader, array $extra): void
    {
        if (! isset($extra['type']) || ! in_array($extra['type'], ['plugin', 'theme'])) {
            return;
        }

        $type = $extra['type'];
        $action = $extra['action'];

        if ($action === 'install') {
            if ($type === 'plugin') {
                $path = $upgrader->skin->result['destination_name'] ?? null;
                if (! $path) {
                    return;
                }

                $data = get_plugin_data($upgrader->skin->result['local_destination'].'/'.$path);
                $this->dispatch(PluginInstalled::class, [
                    'name' => $data['Name'],
                    'version' => $data['Version'],
                    'slug' => $upgrader->result['destination_name'],
                ]);
            } else {
                $slug = $upgrader->skin->result['destination_name'] ?? null;
                if (! $slug) {
                    return;
                }

                wp_clean_themes_cache();
                $theme = wp_get_theme($slug);
                $this->dispatch(ThemeInstalled::class, [
                    'name' => $theme->name,
                    'version' => $theme->version,
                    'slug' => $slug,
                ]);
            }
        } elseif ($action === 'update') {
            if ($type === 'plugin') {
                $slugs = isset($extra['bulk']) && $extra['bulk'] ? $extra['plugins'] : [$upgrader->skin->plugin];
                $_plugins = $this->getPlugins();

                foreach ($slugs as $slug) {
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR.'/'.$slug);
                    $this->dispatch(PluginUpdated::class, [
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'oldVersion' => $_plugins[$slug]['Version'],
                        'slug' => $slug,
                    ]);
                }
            } else {
                $slugs = isset($extra['bulk']) && $extra['bulk'] ? $extra['themes'] : [$upgrader->skin->theme];

                foreach ($slugs as $slug) {
                    $theme = wp_get_theme($slug);
                    $stylesheet = $theme['Stylesheet Dir'].'/style.css';
                    $theme_data = get_file_data($stylesheet, ['Version' => 'Version']);

                    $this->dispatch(ThemeUpdated::class, [
                        'name' => $theme['Name'],
                        'version' => $theme_data['Version'],
                        'oldVersion' => $theme['Version'],
                        'slug' => $slug,
                    ]);
                }
            }
        }
    }

    /**
     * Handle plugin activation.
     *
     * @param  string  $slug  Plugin slug
     * @param  bool  $networkWide  Whether the plugin was activated network wide
     */
    public function handleActivatePlugin(string $slug, bool $networkWide): void
    {
        $_plugins = $this->getPlugins();
        $this->dispatch(PluginActivated::class, [
            'name' => $_plugins[$slug]['Name'],
            'slug' => $slug,
            'networkWide' => $networkWide,
        ]);
    }

    /**
     * Handle plugin deactivation.
     *
     * @param  string  $slug  Plugin slug
     * @param  bool  $networkWide  Whether the plugin was deactivated network wide
     */
    public function handleDeactivatePlugin(string $slug, bool $networkWide): void
    {
        $_plugins = $this->getPlugins();
        $this->dispatch(PluginDeactivated::class, [
            'name' => $_plugins[$slug]['Name'],
            'slug' => $slug,
            'networkWide' => $networkWide,
        ]);
    }

    /**
     * Handle theme activation.
     *
     * @param  string  $name  Theme name
     * @param  string  $theme  Theme object
     */
    public function handleSwitchTheme(string $name, string $theme): void
    {
        $this->dispatch(ThemeActivated::class, [
            'name' => $name,
        ]);
    }

    /**
     * Handle theme deletion.
     */
    public function handleDeleteSiteTransientUpdateThemes(): void
    {
        $backtrace = debug_backtrace();
        $deleteThemeCall = null;

        foreach ($backtrace as $call) {
            if (isset($call['function']) && $call['function'] === 'delete_theme') {
                $deleteThemeCall = $call;
                break;
            }
        }

        if ($deleteThemeCall === null || $deleteThemeCall === []) {
            return;
        }

        $this->dispatch(ThemeDeleted::class, [
            'name' => $deleteThemeCall['args'][0],
        ]);
    }

    /**
     * Handle WordPress core update.
     *
     * @param  string  $newVersion  New WordPress version
     */
    public function handleCoreUpdatedSuccessfully(string $newVersion): void
    {
        global $wp_version, $pagenow;

        $this->dispatch(WordPressUpdated::class, [
            'newVersion' => $newVersion,
            'oldVersion' => $wp_version,
            'autoUpdated' => ($pagenow !== 'update-core.php'),
        ]);
    }

    /**
     * Handle plugin uninstallation.
     */
    public function handlePreOptionUninstallPlugins(): bool
    {
        // Check if we're in a plugin deletion context
        if (
            filter_input(INPUT_GET, 'action') !== 'delete-selected'
            &&
            filter_input(INPUT_POST, 'action2') !== 'delete-selected'
        ) {
            return false;
        }

        // Determine the input type (GET or POST)
        $type = isset($_POST['action2']) ? INPUT_POST : INPUT_GET;

        // Get the plugins that are being deleted
        $plugins = filter_input($type, 'checked', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $_plugins = $this->getPlugins();

        $pluginsToDelete = [];

        foreach ((array) $plugins as $plugin) {
            $pluginsToDelete[$plugin] = $_plugins[$plugin];
        }

        // Store the plugins to delete for later use
        update_option('pollora_plugins_to_delete', $pluginsToDelete);

        return false;
    }

    /**
     * Handle plugin deletion confirmation.
     *
     * @param  mixed  $value  Unused
     * @return mixed
     */
    public function handlePreSetSiteTransientUpdatePlugins($value)
    {
        $pluginsToDelete = get_option('pollora_plugins_to_delete');
        if (! filter_input(INPUT_POST, 'verify-delete') || ! $pluginsToDelete) {
            return $value;
        }

        foreach ($pluginsToDelete as $plugin => $data) {
            $name = $data['Name'];
            $networkWide = isset($data['Network']) && $data['Network'];

            $this->dispatch(PluginDeleted::class, [
                'name' => $name,
                'slug' => $plugin,
                'networkWide' => $networkWide,
            ]);
        }

        delete_option('pollora_plugins_to_delete');

        return $value;
    }

    /**
     * Get all WordPress plugins.
     *
     * @return array<string, array>
     */
    protected function getPlugins(): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        return get_plugins();
    }
}
