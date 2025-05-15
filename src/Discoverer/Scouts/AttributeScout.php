<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Attributes\Attributable;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\Hook\Contracts\Hooks;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\DiscoverConditionFactory;

/**
 * Scout for discovering WordPress hooks.
 *
 * Discovers classes that implement the Hooks interface.
 */
class AttributeScout extends AbstractScout
{
    /**
     * Get the directories to scan.
     *
     * @return array<string> Directories to scan
     */
    protected function directory(): array
    {;
        return [
            app_path(),
            app(RepositoryInterface::class)->getPath()
        ];
    }

    /**
     * Get the type identifier for discovered classes.
     *
     * @return string Type identifier
     */
    protected function type(): string
    {
        return 'attribute';
    }

    /**
     * Define the discovery criteria.
     *
     * @param Discover|DiscoverConditionFactory $discover Discover instance
     * @return Discover|DiscoverConditionFactory Configured discover instance
     */
    protected function criteria(Discover|DiscoverConditionFactory $discover): Discover|DiscoverConditionFactory
    {
        return $discover->implementing(Attributable::class);
    }
}
