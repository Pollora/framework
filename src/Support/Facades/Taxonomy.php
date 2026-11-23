<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Taxonomy\Domain\Contracts\TaxonomyFactoryInterface;

/**
 * Facade of the taxonomy factory.
 *
 * The facade exposes the factory's `make()` only. The fluent methods
 * (`showTagcloud()`, `setExclusive()`...) belong to the taxonomy object that `make()` returns,
 * not to the facade: `Taxonomy::make('genre', 'book')->showAdminColumn()`.
 *
 * @method static \Pollora\Entity\Domain\Model\Taxonomy make(string $slug, string|array $objectType, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5)
 *
 * @see TaxonomyFactoryInterface
 * @see \Pollora\Entity\Domain\Model\Taxonomy
 */
class Taxonomy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TaxonomyFactoryInterface::class;
    }
}
