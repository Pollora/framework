<?php

declare(strict_types=1);

namespace Pollora\Plugin\UI\Console\Commands;

use Illuminate\Console\Command;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;

/**
 * Command to display plugin status information.
 *
 * Shows a comprehensive overview of all plugins including their status,
 * version, author, and other metadata. Provides both summary and detailed views.
 */
class PluginStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pollora:plugin:status 
                            {plugin? : Specific plugin name to show details for}
                            {--active : Show only active plugins}
                            {--inactive : Show only inactive plugins}
                            {--enabled : Show only enabled plugins}
                            {--disabled : Show only disabled plugins}
                            {--detailed : Show detailed information}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display plugin status and information';

    /**
     * Plugin manager instance.
     */
    protected PluginManager $pluginManager;

    /**
     * Create a new command instance.
     *
     * @param  PluginManager  $pluginManager  Plugin manager
     */
    public function __construct(PluginManager $pluginManager)
    {
        parent::__construct();
        $this->pluginManager = $pluginManager;
    }

    /**
     * Execute the console command.
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $pluginName = $this->argument('plugin');

        if ($pluginName) {
            return $this->showPluginDetails($pluginName);
        }

        return $this->showPluginsList();
    }

    /**
     * Show details for a specific plugin.
     *
     * @param  string  $pluginName  Plugin name
     * @return int Command exit code
     */
    protected function showPluginDetails(string $pluginName): int
    {
        try {
            $plugin = $this->pluginManager->findPlugin($pluginName);

            if (! $plugin instanceof PluginModuleInterface) {
                $this->error("Plugin '{$pluginName}' not found.");

                return self::FAILURE;
            }

            $info = $this->pluginManager->getPluginInfo($pluginName);

            if ($this->option('json')) {
                $this->line(json_encode($info, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $this->displayPluginDetails($info);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error retrieving plugin information: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Show list of all plugins with their status.
     *
     * @return int Command exit code
     */
    protected function showPluginsList(): int
    {
        try {
            $plugins = $this->getFilteredPlugins();

            if (empty($plugins)) {
                $this->info('No plugins found matching the criteria.');

                return self::SUCCESS;
            }

            if ($this->option('json')) {
                $this->outputPluginsAsJson($plugins);

                return self::SUCCESS;
            }

            $this->displayPluginsSummary($plugins);

            if ($this->option('detailed')) {
                $this->displayPluginsDetailed($plugins);
            } else {
                $this->displayPluginsTable($plugins);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error retrieving plugins: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get filtered plugins based on command options.
     *
     * @return array Filtered plugin modules
     */
    protected function getFilteredPlugins(): array
    {
        $allPlugins = $this->pluginManager->getAllPluginsAsArray();

        if ($this->option('active')) {
            return $this->pluginManager->getActivePlugins();
        }

        if ($this->option('inactive')) {
            return $this->pluginManager->getInactivePlugins();
        }

        if ($this->option('enabled')) {
            return $this->pluginManager->getEnabledPlugins();
        }

        if ($this->option('disabled')) {
            return $this->pluginManager->getDisabledPlugins();
        }

        return $allPlugins;
    }

    /**
     * Display plugin details.
     *
     * @param  array  $info  Plugin information
     */
    protected function displayPluginDetails(array $info): void
    {
        $this->info("Plugin Details: {$info['name']}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $info['name']],
                ['Description', $info['description'] ?: 'N/A'],
                ['Version', $info['version']],
                ['Author', $info['author'] ?: 'N/A'],
                ['Status', $this->getStatusText($info)],
                ['Path', $info['path']],
                ['Slug', $info['slug']],
                ['Basename', $info['basename']],
                ['Main File', $info['main_file']],
                ['Text Domain', $info['text_domain']],
                ['Domain Path', $info['domain_path'] ?: 'N/A'],
                ['Network Wide', $info['network_wide'] ? 'Yes' : 'No'],
                ['Plugin URI', $info['plugin_uri'] ?: 'N/A'],
                ['Author URI', $info['author_uri'] ?: 'N/A'],
                ['Requires WordPress', $info['requires_wp'] ?: 'N/A'],
                ['Tested up to', $info['tested_up_to'] ?: 'N/A'],
                ['Requires PHP', $info['requires_php'] ?: 'N/A'],
            ]
        );
    }

    /**
     * Display plugins summary.
     *
     * @param  array  $plugins  Plugin modules
     */
    protected function displayPluginsSummary(array $plugins): void
    {
        $totalPlugins = count($plugins);
        $activePlugins = count(array_filter($plugins, fn (PluginModuleInterface $plugin): bool => $plugin->isActive()));
        $enabledPlugins = count(array_filter($plugins, fn (PluginModuleInterface $plugin): bool => $plugin->isEnabled()));

        $this->info('Plugin Summary');
        $this->newLine();
        $this->line("Total Plugins: {$totalPlugins}");
        $this->line("Active Plugins: {$activePlugins}");
        $this->line("Enabled Plugins: {$enabledPlugins}");
        $this->newLine();
    }

    /**
     * Display plugins in table format.
     *
     * @param  array  $plugins  Plugin modules
     */
    protected function displayPluginsTable(array $plugins): void
    {
        $headers = ['Name', 'Version', 'Author', 'Status', 'Path'];
        $rows = [];

        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->getName(),
                $plugin->getVersion(),
                $plugin->getAuthor() ?: 'N/A',
                $this->getStatusText([
                    'active' => $plugin->isActive(),
                    'enabled' => $plugin->isEnabled(),
                ]),
                $plugin->getPath(),
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display plugins with detailed information.
     *
     * @param  array  $plugins  Plugin modules
     */
    protected function displayPluginsDetailed(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $this->displayPluginCard($plugin);
            $this->newLine();
        }
    }

    /**
     * Display plugin card with key information.
     *
     * @param  PluginModuleInterface  $plugin  Plugin module
     */
    protected function displayPluginCard(PluginModuleInterface $plugin): void
    {
        $status = $this->getStatusText([
            'active' => $plugin->isActive(),
            'enabled' => $plugin->isEnabled(),
        ]);

        $this->line("<info>{$plugin->getName()}</info> <comment>v{$plugin->getVersion()}</comment>");
        $this->line("Author: {$plugin->getAuthor()}");
        $this->line("Status: {$status}");
        $this->line("Description: {$plugin->getDescription()}");

        if ($plugin->getPluginUri()) {
            $this->line("Plugin URI: {$plugin->getPluginUri()}");
        }

        $this->line("Path: {$plugin->getPath()}");
        $this->line(str_repeat('-', 60));
    }

    /**
     * Output plugins as JSON.
     *
     * @param  array  $plugins  Plugin modules
     */
    protected function outputPluginsAsJson(array $plugins): void
    {
        $data = [];

        foreach ($plugins as $plugin) {
            $data[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'author' => $plugin->getAuthor(),
                'description' => $plugin->getDescription(),
                'active' => $plugin->isActive(),
                'enabled' => $plugin->isEnabled(),
                'path' => $plugin->getPath(),
                'slug' => $plugin->getSlug(),
                'basename' => $plugin->getBasename(),
                'text_domain' => $plugin->getTextDomain(),
                'network_wide' => $plugin->isNetworkWide(),
                'plugin_uri' => $plugin->getPluginUri(),
                'author_uri' => $plugin->getAuthorUri(),
            ];
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get status text for plugin.
     *
     * @param  array  $info  Plugin information
     * @return string Status text
     */
    protected function getStatusText(array $info): string
    {
        $active = $info['active'] ?? false;
        $enabled = $info['enabled'] ?? false;

        if ($active && $enabled) {
            return '<fg=green>Active & Enabled</fg=green>';
        }

        if ($active) {
            return '<fg=yellow>Active</fg=yellow>';
        }

        if ($enabled) {
            return '<fg=blue>Enabled</fg=blue>';
        }

        return '<fg=red>Inactive</fg=red>';
    }
}
