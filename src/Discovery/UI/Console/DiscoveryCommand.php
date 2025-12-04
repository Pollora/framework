<?php

declare(strict_types=1);

namespace Pollora\Discovery\UI\Console;

use Illuminate\Console\Command;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

/**
 * Discovery Console Command
 *
 * Provides CLI commands for managing the discovery system including
 * running discovery, clearing caches, and inspecting discovered items.
 */
final class DiscoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discovery:run
                            {--clear-cache : Clear discovery cache before running}
                            {--discovery= : Run only specific discovery (optional)}
                            {--stats : Show performance statistics}
                            {--verbose-errors : Show detailed error information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the discovery process to find and register framework components';

    /**
     * Discovery counts collected before applying
     *
     * @var array<string, int>
     */
    private array $discoveryCounts = [];

    /**
     * Execute the console command
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     * @param  ModuleDiscoveryOrchestratorInterface  $moduleOrchestrator  The module discovery orchestrator
     * @return int Command exit code
     */
    public function handle(DiscoveryManager $discoveryManager, ModuleDiscoveryOrchestratorInterface $moduleOrchestrator): int
    {
        $this->info('Starting Pollora Discovery Process...');

        // Show cache status
        $this->showCacheStatus($discoveryManager);

        try {
            // Clear cache if requested
            if ($this->option('clear-cache')) {
                if ($this->isCacheEnabled($discoveryManager)) {
                    $this->info('Clearing discovery cache...');
                    $discoveryManager->clearCache();
                    $this->info('✓ Cache cleared');
                } else {
                    $this->warn('⚠️  Cache is disabled - skipping cache clear');
                }
            }

            // Run specific discovery or all discoveries
            $specificDiscovery = $this->option('discovery');

            if ($specificDiscovery) {
                $this->info("Running discovery: {$specificDiscovery}");

                if (! $discoveryManager->hasDiscovery($specificDiscovery)) {
                    $this->error("Discovery '{$specificDiscovery}' not found!");

                    return self::FAILURE;
                }

                // Run only discovery phase for inspection
                $discoveryManager->discover();
                $items = $discoveryManager->getDiscoveredItems($specificDiscovery);

                $this->info('Found '.count($items)." items for '{$specificDiscovery}'");

                if ($this->confirm('Apply discovered items?', true)) {
                    $discoveryManager->apply();
                    $this->info('✓ Discovery applied');
                }
            } else {
                $this->info('Running all discoveries...');

                // Run discovery phase only to collect counts before applying
                $discoveryManager->discover();
                $this->info('✓ Discovery phase completed');

                // Collect counts before applying
                $discoveryCounts = [];
                foreach ($discoveryManager->getDiscoveries() as $identifier => $discovery) {
                    $discoveryCounts[$identifier] = count($discoveryManager->getDiscoveredItems($identifier));
                }

                // Now apply all discoveries
                $discoveryManager->apply();
                $this->info('✓ All discoveries applied');

                // Also run Laravel module discovery
                $this->info('Running Laravel module discovery...');
                $moduleOrchestrator->discoverLaravelModules();
                $moduleOrchestrator->applyLaravelModules();
                $this->info('✓ Laravel modules discovered and applied');

                // Also run framework module discovery (for app/ directory)
                $this->info('Running framework module discovery...');
                $moduleOrchestrator->discoverFrameworkModules();
                $moduleOrchestrator->applyFrameworkModules();
                $this->info('✓ Framework modules discovered and applied');

                // Store counts for summary
                $this->discoveryCounts = $discoveryCounts;
            }

            // Show summary
            $this->showDiscoverySummary($discoveryManager, $moduleOrchestrator);

            // Show performance statistics if requested
            if ($this->option('stats')) {
                $this->showPerformanceStats($discoveryManager);
            }

            $this->info('Discovery process completed successfully!');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Discovery process failed: '.$e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Show summary of all discoveries and their discovered items
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     * @param  ModuleDiscoveryOrchestratorInterface  $moduleOrchestrator  The module discovery orchestrator
     */
    private function showDiscoverySummary(DiscoveryManager $discoveryManager, ModuleDiscoveryOrchestratorInterface $moduleOrchestrator): void
    {
        $this->info('');
        $this->info('Discovery Summary:');
        $this->info('═════════════════');

        $discoveries = $discoveryManager->getDiscoveries();
        $moduleResults = $moduleOrchestrator->discoverAndReturnLaravelModules();
        $frameworkResults = $moduleOrchestrator->discoverAndReturnFrameworkModules();

        // Aggregate results from main discoveries and module discoveries
        $totals = [];

        // Count items from main discovery manager (use stored counts if available)
        foreach ($discoveries as $identifier => $discovery) {
            $itemCount = $this->discoveryCounts[$identifier] ?? count($discoveryManager->getDiscoveredItems($identifier));
            $totals[$identifier] = ($totals[$identifier] ?? 0) + $itemCount;
        }

        // Count items from Laravel modules
        foreach ($moduleResults as $moduleDiscoveries) {
            foreach ($moduleDiscoveries as $identifier => $items) {
                $itemCount = is_array($items) ? count($items) : 0;
                $totals[$identifier] = ($totals[$identifier] ?? 0) + $itemCount;
            }
        }

        // Count items from framework modules (app/ directory)
        foreach ($frameworkResults as $moduleDiscoveries) {
            foreach ($moduleDiscoveries as $identifier => $items) {
                $itemCount = is_array($items) ? count($items) : 0;
                $totals[$identifier] = ($totals[$identifier] ?? 0) + $itemCount;
            }
        }

        // Display aggregated results
        foreach ($totals as $identifier => $totalCount) {
            $this->line("• {$identifier}: {$totalCount} items");
        }

        $locations = $discoveryManager->getLocations();
        $moduleCount = count($moduleResults);
        $frameworkCount = count($frameworkResults);

        $this->info('');
        $this->info("Scanned {$locations->count()} discovery locations");
        if ($moduleCount > 0) {
            $this->info("Scanned {$moduleCount} Laravel modules");
        }
        if ($frameworkCount > 0) {
            $this->info("Scanned {$frameworkCount} framework modules");
        }
    }

    /**
     * Show performance statistics from the optimized discovery engine
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     */
    private function showPerformanceStats(DiscoveryManager $discoveryManager): void
    {
        $this->info('');
        $this->info('Performance Statistics:');
        $this->info('══════════════════════');

        try {
            // Get the underlying discovery engine
            $engine = $discoveryManager->getEngine();

            if (method_exists($engine, 'getPerformanceStats')) {
                $stats = $engine->getPerformanceStats();

                // Display context stats
                if (isset($stats['context'])) {
                    $context = $stats['context'];
                    $this->line('📊 Discovery Context:');
                    $this->line("  • Classes processed: {$context['total_classes']}");
                    $this->line("  • Discovery executions: {$context['total_discovery_executions']}");
                    $this->line("  • Cache efficiency: {$context['cache_efficiency']}%");

                    if (isset($context['stats'])) {
                        $contextStats = $context['stats'];
                        $this->line("  • Cache hits: {$contextStats['cache_hits']}");
                        $this->line("  • Cache misses: {$contextStats['cache_misses']}");
                        if ($contextStats['errors'] > 0) {
                            $this->line("  • Errors handled: {$contextStats['errors']}");
                        }
                    }
                }

                // Display instance pool stats
                if (isset($stats['instance_pool'])) {
                    $pool = $stats['instance_pool'];
                    $this->line('🏊 Instance Pool:');
                    $this->line("  • Pool size: {$pool['pool_size']} instances");
                    $this->line("  • Hit ratio: {$pool['hit_ratio_percent']}%");
                    $this->line("  • Total requests: {$pool['total_requests']}");

                    if ($pool['circular_dependencies'] > 0) {
                        $this->line("  • Circular deps avoided: {$pool['circular_dependencies']}");
                    }
                    if ($pool['instantiation_errors'] > 0) {
                        $this->line("  • Instantiation errors: {$pool['instantiation_errors']}");
                    }
                }

                // Display static cache stats
                if (isset($stats['static_cache_size'])) {
                    $this->line('💾 Static Cache:');
                    $this->line("  • Cached structure sets: {$stats['static_cache_size']}");
                }

                $this->info('');
                $this->line('💡 Optimizations enabled: Reflection cache, Instance pooling, Unified discovery');

            } else {
                $this->warn('Performance statistics not available (using legacy discovery engine)');
            }

        } catch (\Throwable $e) {
            $this->warn('Unable to retrieve performance statistics: '.$e->getMessage());

            if ($this->option('verbose-errors')) {
                $this->line($e->getTraceAsString());
            }
        }
    }

    /**
     * Show cache status information
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     */
    private function showCacheStatus(DiscoveryManager $discoveryManager): void
    {
        if ($this->isCacheEnabled($discoveryManager)) {
            $this->line('💾 Cache Status: <info>Enabled</info>');
        } else {
            $this->line('💾 Cache Status: <comment>Disabled</comment> (debug mode or NullDiscoverCacheDriver)');
            $this->line('   i️  Discovery will run without persistent caching for better development experience');
        }
        $this->line('');
    }

    /**
     * Check if caching is enabled
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     * @return bool True if cache is enabled, false otherwise
     */
    private function isCacheEnabled(DiscoveryManager $discoveryManager): bool
    {
        $engine = $discoveryManager->getEngine();

        if (! method_exists($engine, 'getCacheDriver')) {
            return false;
        }

        $cacheDriver = $engine->getCacheDriver();

        return $cacheDriver !== null && ! ($cacheDriver instanceof NullDiscoverCacheDriver);
    }
}
