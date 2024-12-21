<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Admin\Page;

/**
 * Facade for WordPress Admin Page functionality.
 *
 * Provides a fluent interface for registering and managing WordPress admin pages
 * with improved type safety and modern PHP syntax.
 *
 * @method static Page addPage(string $pageTitle, string $menuTitle, string $capability, string $slug, mixed $action, string $iconUrl = '', ?int $position = null) Add a top-level admin page
 * @method static Page addSubPage(string $parent, string $pageTitle, string $menuTitle, string $capability, string $slug, mixed $action) Add a sub-level admin page
 *
 * @see \Pollora\Admin\Page
 */
class AdminPage extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.admin.page';
    }
}
