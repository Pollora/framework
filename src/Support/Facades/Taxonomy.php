<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Taxonomy\Infrastructure\Factories\TaxonomyFactory;

/**
 * @method static \Pollora\Entity\Taxonomy make(string $slug, string|array $objectType, string $singular = null, string $plural = null)
 * @method static \Pollora\Entity\Taxonomy showTagcloud()
 * @method static \Pollora\Entity\Taxonomy setShowTagcloud(bool $showTagcloud)
 * @method static \Pollora\Entity\Taxonomy showInQuickEdit()
 * @method static \Pollora\Entity\Taxonomy setShowInQuickEdit(bool $showInQuickEdit)
 * @method static \Pollora\Entity\Taxonomy showAdminColumn()
 * @method static \Pollora\Entity\Taxonomy setShowAdminColumn(bool $showAdminColumn)
 * @method static \Pollora\Entity\Taxonomy setMetaBoxCb(callable|bool|null $metaBoxCb)
 * @method static \Pollora\Entity\Taxonomy setMetaBoxSanitizeCb(?callable $metaBoxSanitizeCb)
 * @method static \Pollora\Entity\Taxonomy setUpdateCountCallback(callable $updateCountCallback)
 * @method static \Pollora\Entity\Taxonomy setDefaultTerm(array|string $defaultTerm)
 * @method static \Pollora\Entity\Taxonomy sort()
 * @method static \Pollora\Entity\Taxonomy setSort(?bool $sort)
 * @method static \Pollora\Entity\Taxonomy setArgs(?array $args)
 * @method static \Pollora\Entity\Taxonomy checkedOntop()
 * @method static \Pollora\Entity\Taxonomy setCheckedOntop(bool $checkedOntop)
 * @method static \Pollora\Entity\Taxonomy exclusive()
 * @method static \Pollora\Entity\Taxonomy setExclusive(bool $exclusive)
 * @method static \Pollora\Entity\Taxonomy allowHierarchy()
 * @method static \Pollora\Entity\Taxonomy setAllowHierarchy(bool $allowHierarchy)
 *
 * @see \Pollora\Entity\Taxonomy
 */
class Taxonomy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TaxonomyFactory::class;
    }
}
