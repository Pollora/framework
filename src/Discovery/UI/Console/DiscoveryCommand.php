<?php

declare(strict_types=1);

namespace Pollora\Discovery\UI\Console;

use Illuminate\Console\Command;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;

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
                            {--discovery= : Run only specific discovery (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the discovery process to find and register framework components';

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

        try {
            // Clear cache if requested
            if ($this->option('clear-cache')) {
                $this->info('Clearing discovery cache...');
                $discoveryManager->clearCache();
                $this->info('✓ Cache cleared');
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
                $discoveryManager->run();
                $this->info('✓ All discoveries completed');
                
                // Also run Laravel module discovery
                $this->info('Running Laravel module discovery...');
                $moduleOrchestrator->discoverLaravelModules();
                $moduleOrchestrator->applyLaravelModules();
                $this->info('✓ Laravel modules discovered and applied');
            }

            // Show summary
            $this->showDiscoverySummary($discoveryManager, $moduleOrchestrator);

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
        
        // Aggregate results from main discoveries and module discoveries
        $totals = [];
        
        // Count items from main discovery manager
        foreach ($discoveries as $identifier => $discovery) {
            $itemCount = count($discoveryManager->getDiscoveredItems($identifier));
            $totals[$identifier] = ($totals[$identifier] ?? 0) + $itemCount;
        }
        
        // Count items from Laravel modules
        foreach ($moduleResults as $moduleName => $moduleDiscoveries) {
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
        
        $this->info('');
        $this->info("Scanned {$locations->count()} discovery locations");
        if ($moduleCount > 0) {
            $this->info("Scanned {$moduleCount} Laravel modules");
        }
    }
}
