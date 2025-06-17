<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\WpRest\AbstractWpRestRoute;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering WordPress REST API route classes.
 *
 * This scout finds all classes that extend AbstractWpRestRoute across
 * the application, modules, themes, and plugins for automatic
 * WordPress REST API endpoint registration.
 */
final class WpRestRoutesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractWpRestRoute::class);
    }
}
