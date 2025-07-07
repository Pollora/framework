<?php

declare(strict_types=1);

namespace Pollora\Plugin\UI\Console\Commands;

use Illuminate\Console\Command;
use Pollora\Plugin\Application\Services\PluginManager;
use Pollora\Plugin\Domain\Contracts\PluginModuleInterface;

/**
 * Command to list all available plugins.
 *
 * Displays a list of plugins with filtering and sorting options.
 * Provides various output formats including table, list, and JSON.
 */
class PluginListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pollora:plugin:list 
                            {--active : Show only active plugins}
                            {--inactive : Show only inactive plugins}
                            {--enabled : Show only enabled plugins}
                            {--disabled : Show only disabled plugins}
                            {--network : Show only network-wide plugins}
                            {--author= : Filter by author}
                            {--search= : Search in plugin names and descriptions}
                            {--sort=name : Sort by (name, version, author, status)}
                            {--direction=asc : Sort direction (asc, desc)}
                            {--format=table : Output format (table, list, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available plugins with filtering and sorting options';

    /**
     * Plugin manager instance.
     *
     * @var PluginManager
     */
    protected PluginManager $pluginManager;

    /**
     * Create a new command instance.
     *
     * @param PluginManager $pluginManager Plugin manager
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
        try {
            $plugins = $this->getFilteredAndSortedPlugins();

            if (empty($plugins)) {
                $this->info('No plugins found matching the criteria.');
                return self::SUCCESS;
            }

            $format = $this->option('format');

            match ($format) {
                'json' => $this->outputAsJson($plugins),
                'list' => $this->outputAsList($plugins),
                default => $this->outputAsTable($plugins),
            };

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error listing plugins: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Get filtered and sorted plugins.
     *
     * @return array Filtered and sorted plugin modules
     */
    protected function getFilteredAndSortedPlugins(): array
    {
        // Get base collection
        $collection = $this->pluginManager->collect();

        // Apply filters
        $collection = $this->applyFilters($collection);

        // Apply search
        if ($search = $this->option('search')) {
            $collection = $this->applySearch($collection, $search);
        }

        // Apply sorting
        $collection = $this->applySorting($collection);

        return $collection->toArray();
    }

    /**
     * Apply filters to the plugin collection.
     *
     * @param \Pollora\Plugin\Domain\Support\PluginCollection $collection Plugin collection
     * @return \Pollora\Plugin\Domain\Support\PluginCollection Filtered collection
     */
    protected function applyFilters($collection)
    {
        if ($this->option('active')) {
            $collection = $collection->active();
        }

        if ($this->option('inactive')) {
            $collection = $collection->inactive();
        }

        if ($this->option('enabled')) {
            $collection = $collection->enabled();
        }

        if ($this->option('disabled')) {
            $collection = $collection->disabled();
        }

        if ($this->option('network')) {
            $collection = $collection->filter(function (PluginModuleInterface $plugin): bool {
                return $plugin->isNetworkWide();
            });
        }

        if ($author = $this->option('author')) {
            $collection = $collection->filter(function (PluginModuleInterface $plugin) use ($author): bool {
                return $plugin->getAuthor() === $author;
            });
        }

        return $collection;
    }

    /**
     * Apply search to the plugin collection.
     *
     * @param \Pollora\Plugin\Domain\Support\PluginCollection $collection Plugin collection
     * @param string $search Search term
     * @return \Pollora\Plugin\Domain\Support\PluginCollection Filtered collection
     */
    protected function applySearch($collection, string $search)
    {
        return $collection->filter(function (PluginModuleInterface $plugin) use ($search): bool {
            $searchLower = strtolower($search);
            
            return str_contains(strtolower($plugin->getName()), $searchLower) ||
                   str_contains(strtolower($plugin->getDescription()), $searchLower) ||
                   str_contains(strtolower($plugin->getAuthor()), $searchLower);
        });
    }

    /**
     * Apply sorting to the plugin collection.
     *
     * @param \Pollora\Plugin\Domain\Support\PluginCollection $collection Plugin collection
     * @return \Pollora\Plugin\Domain\Support\PluginCollection Sorted collection
     */
    protected function applySorting($collection)
    {
        $sortBy = $this->option('sort');
        $direction = $this->option('direction');

        return match ($sortBy) {
            'version' => $collection->sortBy(function (PluginModuleInterface $plugin): string {
                return $plugin->getVersion();
            }, SORT_REGULAR, $direction === 'desc'),
            'author' => $collection->sortBy(function (PluginModuleInterface $plugin): string {
                return $plugin->getAuthor();
            }, SORT_REGULAR, $direction === 'desc'),
            'status' => $collection->sortBy(function (PluginModuleInterface $plugin): string {
                if ($plugin->isActive() && $plugin->isEnabled()) {
                    return 'active-enabled';
                }
                if ($plugin->isActive()) {
                    return 'active';
                }
                if ($plugin->isEnabled()) {
                    return 'enabled';
                }
                return 'inactive';
            }, SORT_REGULAR, $direction === 'desc'),
            default => $collection->sortByName($direction),
        };
    }

    /**
     * Output plugins as table.
     *
     * @param array $plugins Plugin modules
     * @return void
     */
    protected function outputAsTable(array $plugins): void
    {
        $this->displaySummary($plugins);
        
        $headers = ['Name', 'Version', 'Author', 'Status', 'Text Domain'];
        $rows = [];

        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->getName(),
                $plugin->getVersion(),
                $plugin->getAuthor() ?: 'N/A',
                $this->getStatusBadge($plugin),
                $plugin->getTextDomain(),
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Output plugins as list.
     *
     * @param array $plugins Plugin modules
     * @return void
     */
    protected function outputAsList(array $plugins): void
    {
        $this->displaySummary($plugins);

        foreach ($plugins as $plugin) {
            $status = $this->getStatusBadge($plugin);
            $this->line("• <info>{$plugin->getName()}</info> <comment>v{$plugin->getVersion()}</comment> - {$status}");
            
            if ($plugin->getDescription()) {
                $this->line("  {$plugin->getDescription()}");
            }
            
            if ($plugin->getAuthor()) {
                $this->line("  <fg=gray>By: {$plugin->getAuthor()}</fg=gray>");
            }
            
            $this->newLine();
        }
    }

    /**
     * Output plugins as JSON.
     *
     * @param array $plugins Plugin modules
     * @return void
     */
    protected function outputAsJson(array $plugins): void
    {
        $data = [
            'total' => count($plugins),
            'filters' => $this->getActiveFilters(),
            'plugins' => [],
        ];

        foreach ($plugins as $plugin) {
            $data['plugins'][] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'author' => $plugin->getAuthor(),
                'description' => $plugin->getDescription(),
                'active' => $plugin->isActive(),
                'enabled' => $plugin->isEnabled(),
                'network_wide' => $plugin->isNetworkWide(),
                'text_domain' => $plugin->getTextDomain(),
                'slug' => $plugin->getSlug(),
                'basename' => $plugin->getBasename(),
                'path' => $plugin->getPath(),
                'plugin_uri' => $plugin->getPluginUri(),
                'author_uri' => $plugin->getAuthorUri(),
            ];
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Display summary information.
     *
     * @param array $plugins Plugin modules
     * @return void
     */
    protected function displaySummary(array $plugins): void
    {
        $total = count($plugins);
        $active = count(array_filter($plugins, fn (PluginModuleInterface $plugin): bool => $plugin->isActive()));
        $enabled = count(array_filter($plugins, fn (PluginModuleInterface $plugin): bool => $plugin->isEnabled()));
        $networkWide = count(array_filter($plugins, fn (PluginModuleInterface $plugin): bool => $plugin->isNetworkWide()));

        $this->info("Plugin Summary");
        $this->line("Total: {$total} | Active: {$active} | Enabled: {$enabled} | Network-wide: {$networkWide}");
        
        if ($filters = $this->getActiveFiltersText()) {
            $this->line("Filters: {$filters}");
        }
        
        $this->newLine();
    }

    /**
     * Get status badge for plugin.
     *
     * @param PluginModuleInterface $plugin Plugin module
     * @return string Status badge
     */
    protected function getStatusBadge(PluginModuleInterface $plugin): string
    {
        if ($plugin->isActive() && $plugin->isEnabled()) {
            return '<fg=green>●</fg=green> Active';
        }

        if ($plugin->isActive()) {
            return '<fg=yellow>●</fg=yellow> Active';
        }

        if ($plugin->isEnabled()) {
            return '<fg=blue>●</fg=blue> Enabled';
        }

        return '<fg=red>●</fg=red> Inactive';
    }

    /**
     * Get active filters as array.
     *
     * @return array Active filters
     */
    protected function getActiveFilters(): array
    {
        $filters = [];

        if ($this->option('active')) {
            $filters[] = 'active';
        }
        if ($this->option('inactive')) {
            $filters[] = 'inactive';
        }
        if ($this->option('enabled')) {
            $filters[] = 'enabled';
        }
        if ($this->option('disabled')) {
            $filters[] = 'disabled';
        }
        if ($this->option('network')) {
            $filters[] = 'network-wide';
        }
        if ($author = $this->option('author')) {
            $filters[] = "author:{$author}";
        }
        if ($search = $this->option('search')) {
            $filters[] = "search:{$search}";
        }

        return $filters;
    }

    /**
     * Get active filters as text.
     *
     * @return string Active filters text
     */
    protected function getActiveFiltersText(): string
    {
        $filters = $this->getActiveFilters();
        
        return implode(', ', $filters);
    }
}