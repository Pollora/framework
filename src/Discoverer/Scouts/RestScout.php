<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\WpRest\AbstractWpRestRoute;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\DiscoverConditionFactory;

/**
 * Scout for discovering WordPress hooks.
 *
 * Discovers classes that implement the Hooks interface.
 */
class RestScout extends AbstractScout
{
    /**
     * Get the directories to scan.
     *
     * @return array<string> Directories to scan
     */
    protected function directory(): array
    {
        return [
            app_path(),
            app(RepositoryInterface::class)->getPath(),
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
     * @param  Discover|DiscoverConditionFactory  $discover  Discover instance
     * @return Discover|DiscoverConditionFactory Configured discover instance
     */
    protected function criteria(Discover|DiscoverConditionFactory $discover): Discover|DiscoverConditionFactory
    {
        return $discover->extending(AbstractWpRestRoute::class);
    }
}
