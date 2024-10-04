<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollen\Admin\Page;

/**
 * @method static Page addPage(string $pageTitle, string $menuTitle, string $capability, string $slug, mixed $action, string $iconUrl = '', ?int $position = null)
 * @method static Page addSubPage(string $parent, string $pageTitle, string $menuTitle, string $capability, string $slug, mixed $action)
 */
class AdminPage extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.admin.page';
    }
}
