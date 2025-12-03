<?php

declare(strict_types=1);

namespace Pollora\Discovery\UI\Console;

use Illuminate\Console\Command;
use Pollora\Discovery\Application\Services\DiscoveryManager;

/**
 * Discovery Clear Command
 *
 * Console command for clearing discovery caches.
 */
final class DiscoveryClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discovery:clear
                            {--reflection-cache : Clear reflection cache}
                            {--instance-pool : Clear instance pool}
                            {--static-cache : Clear static structures cache}
                            {--all : Clear all caches (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all discovery caches';

    /**
     * Execute the console command
     *
     * @param  DiscoveryManager  $discoveryManager  The discovery manager
     * @return int Command exit code
     */
    public function handle(DiscoveryManager $discoveryManager): int
    {
        try {
            // Determine which caches to clear
            $clearAll = $this->option('all') || (!$this->option('reflection-cache') && !$this->option('instance-pool') && !$this->option('static-cache'));
            
            if ($clearAll) {
                $this->info('Clearing all discovery caches...');
                $this->clearAllCaches($discoveryManager);
            } else {
                $this->info('Clearing selected discovery caches...');
                $this->clearSelectedCaches($discoveryManager);
            }

            $this->info('✓ Discovery caches cleared successfully');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to clear discovery caches: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Clear all discovery caches
     */
    private function clearAllCaches(DiscoveryManager $discoveryManager): void
    {
        // Clear traditional discovery manager cache
        $discoveryManager->clearCache();
        
        // Clear optimized engine caches
        $engine = $discoveryManager->getEngine();
        
        if (method_exists($engine, 'getContext')) {
            $this->line('  • Clearing reflection cache...');
            $engine->getContext()->getReflectionCache()->clearCache();
        }
        
        if (method_exists($engine, 'getInstancePool')) {
            $this->line('  • Clearing instance pool...');
            $engine->getInstancePool()->clearAll();
        }
        
        if (method_exists($engine, 'clearStructuresCache')) {
            $this->line('  • Clearing static structures cache...');
            $engine::clearStructuresCache();
        }
    }

    /**
     * Clear only selected caches based on options
     */
    private function clearSelectedCaches(DiscoveryManager $discoveryManager): void
    {
        $engine = $discoveryManager->getEngine();
        
        if ($this->option('reflection-cache')) {
            $this->line('  • Clearing reflection cache...');
            if (method_exists($engine, 'getContext')) {
                $engine->getContext()->getReflectionCache()->clearCache();
            }
        }
        
        if ($this->option('instance-pool')) {
            $this->line('  • Clearing instance pool...');
            if (method_exists($engine, 'getInstancePool')) {
                $engine->getInstancePool()->clearAll();
            }
        }
        
        if ($this->option('static-cache')) {
            $this->line('  • Clearing static structures cache...');
            if (method_exists($engine, 'clearStructuresCache')) {
                $engine::clearStructuresCache();
            }
        }
    }
}
