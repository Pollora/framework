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
            $this->info('Clearing discovery caches...');

            $discoveryManager->clearCache();

            $this->info('✓ Discovery caches cleared successfully');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to clear discovery caches: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
