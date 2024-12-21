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
 * WordPress authentication with Laravel's authentication system.
 *
 * @implements UserProvider
 */
class WordPressUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param mixed $identifier
     * @param string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // WordPress doesn't use remember tokens by default
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param Authenticatable $user
     * @param string $token
     */
    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // WordPress doesn't use remember tokens by default
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $user = wp_authenticate($credentials['username'], $credentials['password']);

        return $user instanceof WP_Error ? null : User::find($user->ID);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
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
     * @param Authenticatable $user
     * @param array $credentials
     * @param bool $force
     * @return bool
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): bool
    {
        // WordPress handles password rehashing automatically
        return false;
    }
}
