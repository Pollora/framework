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
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.admin.page';
    }
}
