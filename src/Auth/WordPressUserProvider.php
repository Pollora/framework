<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Pollora\Models\User;
use WP_Error;

/**
 * WordPress user provider for Laravel authentication.
 *
 * This class implements Laravel's UserProvider interface to integrate
 * WordPress user management with Laravel's authentication system.
 * It handles user retrieval, credential validation, and token management.
 *
 * @implements UserProvider
 */
class WordPressUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier  The user ID
     * @return Authenticatable|null The user instance or null if not found
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * Note: WordPress doesn't use remember tokens by default.
     *
     * @param  mixed  $identifier  The user ID
     * @param  string  $token  The remember token
     * @return Authenticatable|null Always returns null for WordPress
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * Note: WordPress doesn't use remember tokens by default.
     *
     * @param  Authenticatable  $user  The user instance
     * @param  string  $token  The new remember token
     */
    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // WordPress doesn't use remember tokens by default
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, string>  $credentials  The user credentials
     * @return Authenticatable|null The authenticated user or null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $user = wp_authenticate($credentials['username'], $credentials['password']);

        return $user instanceof WP_Error ? null : User::find($user->ID);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  Authenticatable  $user  The user to validate
     * @param  array<string, string>  $credentials  The credentials to check
     * @return bool True if credentials are valid
     */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        if ($user instanceof User) {
            return wp_check_password($credentials['password'], $user->user_pass, $user->ID);
        }

        return false;
    }

    /**
     * Rehash the user's password if required.
     *
     * Note: WordPress handles password rehashing automatically.
     *
     * @param  Authenticatable  $user  The user instance
     * @param  array<string, string>  $credentials  The user credentials
     * @param  bool  $force  Force rehashing regardless of need
     * @return bool Always returns false for WordPress
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): bool
    {
        return false;
    }
}
