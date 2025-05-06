<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Attributes\AttributeProcessor;
use Pollora\Discoverer\Contracts\DiscoveryRegistry;
use Pollora\PostType\AbstractPostType;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\DiscoverConditionFactory;

/**
 * Scout for discovering post types.
 *
 * Discovers classes that extend AbstractPostType.
 */
class PostTypeScout extends AbstractScout
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
        return 'post_type';
    }

    /**
     * Define the discovery criteria.
     *
     * @param Discover|DiscoverConditionFactory $discover Discover instance
     * @return Discover|DiscoverConditionFactory Configured discover instance
     */
    protected function criteria(Discover|DiscoverConditionFactory $discover): Discover|DiscoverConditionFactory
    {
        return $discover->extending(AbstractPostType::class);
    }
}
