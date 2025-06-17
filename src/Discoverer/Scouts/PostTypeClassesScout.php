<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\PostType\Domain\Models\AbstractPostType;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom post type classes.
 *
 * This scout finds all classes that extend AbstractPostType across
 * the application, modules, themes, and plugins for automatic
 * WordPress post type registration.
 */
final class PostTypeClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractPostType::class);
    }
}
