<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\Ajax\AjaxAction;
use Pollora\Ajax\Factory\AjaxFactory;
use Pollora\Ajax\Infrastructure\Providers\AjaxServiceProvider;

/**
 * Laravel facade for WordPress AJAX action management.
 *
 * Resolves the {@see AjaxFactory} from the service container (key `wp.ajax`)
 * and proxies calls to it. Actions default to logged-in users only;
 * chain `->forAllUsers()` or `->forGuestUsers()` to change targeting.
 *
 * Usage:
 *     Ajax::listen('my_action', $callback);                // logged-in only (default)
 *     Ajax::listen('public', $callback)->forAllUsers();    // everyone
 *     Ajax::listen('guest', $callback)->forGuestUsers();   // guests only
 *
 * @method static AjaxAction listen(string $action, callable|string $callback) Register an AJAX action handler.
 *
 * @see AjaxFactory
 * @see AjaxServiceProvider
 */
class Ajax extends Facade
{
    /**
     * Get the container binding key for the underlying service.
     *
     * @return string The `wp.ajax` key resolving to {@see AjaxFactory}.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.ajax';
    }
}
