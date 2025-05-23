<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\PostType\Infrastructure\Factories\PostTypeFactory;

/**
 * @method static object make(string $slug, string $singular = null, string $plural = null, array $args = [])
 * @method static object excludeFromSearch()
 * @method static object setExcludeFromSearch(?bool $excludeFromSearch)
 * @method static object chronological()
 * @method static object setHierarchical(bool $hierarchical)
 * @method static object showInAdminBar()
 * @method static object setShowInAdminBar(?bool $showInAdminBar)
 * @method static object setMenuPosition(?int $menuPosition)
 * @method static object setMenuIcon(?string $menuIcon)
 * @method static object setCapabilityType(string $capabilityType)
 * @method static object mapMetaCap()
 * @method static object setMapMetaCap(bool $mapMetaCap)
 * @method static object setRegisterMetaBoxCb(?callable $registerMetaBoxCb)
 * @method static object setTaxonomies(array $taxonomies)
 * @method static object hasArchive(bool|string $hasArchive = true)
 * @method static object canExport()
 * @method static object setCanExport(bool $canExport)
 * @method static object deletedWithUser()
 * @method static object setDeleteWithUser(?bool $deleteWithUser)
 * @method static object setRestController(string|object $restController)
 * @method static object setTemplate(array $template)
 * @method static object setTemplateLock(bool|string $templateLock)
 * @method static object supports(bool|array $supports)
 * @method static object adminFilters(array $adminFilters)
 * @method static object setArchive(array $archive)
 * @method static object enableBlockEditor()
 * @method static object setBlockEditor(bool $blockEditor)
 * @method static object enableDashboardActivity()
 * @method static object setDashboardActivity(bool $dashboardActivity)
 * @method static object titlePlaceholder(string $enterTitleHere)
 * @method static object setFeaturedImage(string $featuredImage)
 * @method static object setQuickEdit(bool $quickEdit)
 * @method static object enableQuickEdit()
 * @method static object setShowInFeed(bool $showInFeed)
 * @method static object showInFeed()
 * @method static object siteFilters(array $siteFilters)
 * @method static object siteSortables(array $siteSortables)
 *
 * @see \Pollora\PostType\Infrastructure\Factories\PostTypeFactory
 * @see \Pollora\Entity\PostType
 */
class PostType extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PostTypeFactory::class;
    }
}
