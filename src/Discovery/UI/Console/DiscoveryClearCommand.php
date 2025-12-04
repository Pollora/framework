<?php

declare(strict_types=1);

namespace Pollora\Discovery\UI\Console;

use Illuminate\Console\Command;
use Pollora\Discovery\Application\Services\DiscoveryManager;
use Spatie\StructureDiscoverer\Cache\NullDiscoverCacheDriver;

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
            // Check if cache is enabled
            if (! $this->isCacheEnabled($discoveryManager)) {
                $this->warn('⚠️  Cache is disabled (debug mode or NullDiscoverCacheDriver)');
                $this->line('   No persistent cache to clear.');
                $this->line('');
            }

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
     * Clear persistent discovery cache only
     *
     * Only clears persistent cache that survives between requests.
     */
    private function clearAllCaches(DiscoveryManager $discoveryManager): void
    {
        // Clear persistent caches only through the discovery manager
        $this->line('  • Clearing persistent discovery cache...');
        $discoveryManager->clearCache();

        // Get the engine for Spatie cache clearing
        $engine = $discoveryManager->getEngine();

        // Clear Spatie structure discoverer cache (the only persistent cache)
        $this->line('  • Clearing structure discoverer cache...');
        $this->clearSpatieCache($engine);
    }

    /**
     * Clear Spatie's structure discoverer cache
     *
     * @param  object  $engine  The discovery engine instance
     */
    private function clearSpatieCache(object $engine): void
    {
        if (! method_exists($engine, 'getCacheDriver')) {
            $this->line('    <comment>Spatie cache driver not available</comment>');

            return;
        }

        $cacheDriver = $engine->getCacheDriver();

        if ($cacheDriver === null) {
            $this->line('    <comment>No cache driver configured</comment>');

            return;
        }

        if (! method_exists($cacheDriver, 'forget')) {
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

        if ($cleared > 0) {
            $this->line("    <info>Cleared cache for {$cleared} discovery locations</info>");
        } else {
            $this->line('    <comment>No cache entries to clear</comment>');
        }
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
