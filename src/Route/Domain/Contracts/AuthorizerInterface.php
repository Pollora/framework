<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Interface for authorization services.
 *
 * Defines methods for checking if a user is authorized based on specific capabilities.
 */
interface AuthorizerInterface
{
    /**
     * Check if the current user is authorized.
     *
     * @param  string  $capability  The capability to check
     * @return bool True if user is authorized, false otherwise
     */
    public function isAuthorized(string $capability): bool;

    /**
     * Check if a user is logged in.
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function isLoggedIn(): bool;
}
