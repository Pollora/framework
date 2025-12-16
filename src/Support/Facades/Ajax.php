<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress AJAX functionality.
 *
 * Provides a clean interface for registering and handling WordPress AJAX actions
 * with improved type safety and modern PHP syntax.
 *
 * @method static \Pollora\Ajax\Domain\Models\AjaxAction listen(string $action, callable|string $callback) Register an AJAX action handler
 *
 * @see \Pollora\Ajax\Infrastructure\Services\AjaxFactory
 */
class Ajax extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.ajax';
    }
}
