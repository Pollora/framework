<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Attributes\Attributable;
use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering classes that implement the Attributable interface.
 *
 * This scout finds all classes across the application, modules, and themes
 * that implement the Attributable interface, enabling automatic processing
 * of PHP 8 attributes for framework features.
 */
final class AttributableClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->implementing(Attributable::class);
    }
}
