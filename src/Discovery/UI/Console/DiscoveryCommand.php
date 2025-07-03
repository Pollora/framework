<?php

declare(strict_types=1);

namespace Pollora\Discovery\UI\Console;

use Illuminate\Console\Command;
use Pollora\Discovery\Application\Services\DiscoveryManager;

/**
 * Discovery Console Command
 *
 * Provides CLI commands for managing the discovery system including
 * running discovery, clearing caches, and inspecting discovered items.
 *
 * @package Pollora\Discovery\UI\Console
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
     * @param DiscoveryManager $discoveryManager The discovery manager
     *
     * @return int Command exit code
     */
    public function handle(DiscoveryManager $discoveryManager): int
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
                
                if (!$discoveryManager->hasDiscovery($specificDiscovery)) {
                    $this->error("Discovery '{$specificDiscovery}' not found!");
                    return self::FAILURE;
                }

                // Run only discovery phase for inspection
                $discoveryManager->discover();
                $items = $discoveryManager->getDiscoveredItems($specificDiscovery);
                
                $this->info("Found " . count($items) . " items for '{$specificDiscovery}'");
                
                if ($this->confirm('Apply discovered items?', true)) {
                    $discoveryManager->apply();
                    $this->info('✓ Discovery applied');
                }
            } else {
                $this->info('Running all discoveries...');
                $discoveryManager->run();
                $this->info('✓ All discoveries completed');
            }

            // Show summary
            $this->showDiscoverySummary($discoveryManager);

            $this->info('Discovery process completed successfully!');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Discovery process failed: ' . $e->getMessage());
            
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Show summary of all discoveries and their discovered items
     *
     * @param DiscoveryManager $discoveryManager The discovery manager
     *
     * @return void
     */
    private function showDiscoverySummary(DiscoveryManager $discoveryManager): void
    {
        $this->info('');
        $this->info('Discovery Summary:');
        $this->info('═════════════════');

        $discoveries = $discoveryManager->getDiscoveries();
        
        foreach ($discoveries as $identifier => $discovery) {
            $itemCount = count($discoveryManager->getDiscoveredItems($identifier));
            $this->line("• {$identifier}: {$itemCount} items");
        }

        $locations = $discoveryManager->getLocations();
        $this->info('');
        $this->info("Scanned {$locations->count()} discovery locations");
    }
}