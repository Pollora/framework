<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Hook\Domain\Contracts\Hooks;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering WordPress hook classes.
 *
 * This scout finds all classes that implement the Hooks interface,
 * typically located in Domain/Hooks directories within modules,
 * themes, and the main application.
 */
final class HookClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->implementing(Hooks::class);
    }
}
