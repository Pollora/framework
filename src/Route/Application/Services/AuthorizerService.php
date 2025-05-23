<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\AuthorizerInterface;

/**
 * Service for checking user authorization in WordPress.
 */
class AuthorizerService implements AuthorizerInterface
{
    /**
     * Check if the current user is authorized.
     *
     * Verifies that the user is both logged in and has the required capability.
     *
     * @param  string  $capability  The capability to check
     * @return bool True if user is authorized, false otherwise
     */
    public function isAuthorized(string $capability): bool
    {
        return $this->isLoggedIn() && $this->hasCapability($capability);
    }

    /**
     * Check if a user is logged in.
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function isLoggedIn(): bool
    {
        return $this->isWordPressFunctionAvailable('is_user_logged_in') && is_user_logged_in();
    }

    /**
     * Check if the current user has a specific capability.
     *
     * @param  string  $capability  The capability to check
     * @return bool True if the user has the capability
     */
    protected function hasCapability(string $capability): bool
    {
        return $this->isWordPressFunctionAvailable('current_user_can') && current_user_can($capability);
    }

    /**
     * Check if a WordPress function is available.
     *
     * @param  string  $function  The function name to check
     * @return bool True if the function exists
     */
    protected function isWordPressFunctionAvailable(string $function): bool
    {
        return function_exists($function);
    }
}
