<?php

declare(strict_types=1);

namespace Pollora\Ajax;

/**
 * Factory for creating WordPress AJAX handlers with a fluent interface.
 *
 * This class provides a fluent interface for creating WordPress AJAX handlers,
 * making it easier to configure and manage AJAX actions in a Laravel-like way.
 * It integrates with WordPress's AJAX system while providing Laravel's dependency
 * injection and response handling capabilities.
 */
class AjaxFactory
{
    /**
     * Register an AJAX action handler.
     *
     * Creates a new Ajax instance to handle a specific WordPress AJAX action.
     * The callback can be either a closure or a class method string (Controller@method).
     * The callback will be resolved through Laravel's service container, enabling
     * dependency injection.
     *
     * @param  string  $action  The WordPress AJAX action name to listen for
     * @param  callable|string  $callback  The callback to handle the AJAX request
     * @return \Pollora\Ajax\Ajax Returns an Ajax instance for method chaining
     *
     * @example
     * ```php
     * // Using a closure
     * $ajax->listen('my_action', function () {
     *     return response()->json(['status' => 'success']);
     * });
     *
     * // Using a controller
     * $ajax->listen('my_action', 'AjaxController@handleAction');
     * ```
     */
    public function listen(string $action, callable|string $callback): \Pollora\Ajax\Ajax
    {
        return new Ajax($action, $callback);
    }
}
