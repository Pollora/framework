<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Scouts;

use Pollora\Discoverer\Infrastructure\Services\AbstractPolloraScout;
use Pollora\Taxonomy\Domain\Models\AbstractTaxonomy;
use Spatie\StructureDiscoverer\Discover;

/**
 * Scout for discovering custom taxonomy classes.
 *
 * This scout finds all classes that extend AbstractTaxonomy across
 * the application, modules, themes, and plugins for automatic
 * WordPress taxonomy registration.
 */
final class TaxonomyClassesScout extends AbstractPolloraScout
{
    /**
     * {@inheritDoc}
     */
    protected function criteria(Discover $discover): Discover
    {
        return $discover
            ->classes()
            ->extending(AbstractTaxonomy::class);
    }
}
