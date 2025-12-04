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
    protected $signature = 'discovery:clear';

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
            $this->info('Clearing all discovery caches...');
            $this->clearAllCaches($discoveryManager);
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
        // Clear all caches through the discovery manager
        $this->line('  • Clearing discovery manager cache...');
        $discoveryManager->clearCache();
        
        // Get the engine for additional cache clearing
        $engine = $discoveryManager->getEngine();
        
        // Clear reflection cache
        if (method_exists($engine, 'getContext')) {
            $this->line('  • Clearing reflection cache...');
            $engine->getContext()->getReflectionCache()->clearCache();
        }
        
        // Clear instance pool
        if (method_exists($engine, 'getInstancePool')) {
            $this->line('  • Clearing instance pool...');
            $engine->getInstancePool()->clearAll();
        }
        
        // Clear static structures cache
        if (method_exists($engine, 'clearStructuresCache')) {
            $this->line('  • Clearing static structures cache...');
            $engine::clearStructuresCache();
        }
        
        // Clear Spatie structure discoverer cache
        $this->line('  • Clearing Spatie structure discoverer cache...');
        $this->clearSpatieCache($engine);
    }

    /**
     * Clear Spatie's structure discoverer cache
     *
     * @param  object  $engine  The discovery engine instance
     */
    private function clearSpatieCache(object $engine): void
    {
        if (!method_exists($engine, 'getCacheDriver')) {
            $this->line('    <comment>Spatie cache driver not available</comment>');
            return;
        }
        
        $cacheDriver = $engine->getCacheDriver();
        
        if ($cacheDriver === null) {
            $this->line('    <comment>No cache driver configured</comment>');
            return;
        }
        
        if (!method_exists($cacheDriver, 'forget')) {
            $this->line('    <comment>Cache driver does not support clearing</comment>');
            return;
        }
        
        // Clear cache for each discovery location
        $locations = $engine->getLocations();
        $cleared = 0;
        
        foreach ($locations as $location) {
            $cacheId = 'discovery_'.md5($location->getPath());
            try {
                $cacheDriver->forget($cacheId);
                $cleared++;
            } catch (\Throwable $e) {
                $this->line("    <warning>Failed to clear cache for location: {$location->getPath()}</warning>");
            }
        }
        
        $this->line("    <info>Cleared cache for {$cleared} discovery locations</info>");
    }
}
