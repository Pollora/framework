<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Request;
use Pollora\Models\User;
use WP_Error;

/**
 * WordPress authentication guard for Laravel.
 *
 * This class implements Laravel's StatefulGuard interface to provide WordPress
 * authentication functionality within Laravel's authentication system.
 * It handles user authentication, session management, and WordPress-specific
 * authentication features.
 *
 * @implements StatefulGuard
 * @uses GuardHelpers
 */
class WordPressGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The last attempted user authentication.
     *
     * @var User|null
     */
    private ?User $lastAttempted = null;

    /**
     * Check if a user is currently authenticated.
     *
     * @return bool True if user is logged in, false otherwise
     */
    public function check(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null The authenticated user or null
     */
    public function user(): ?Authenticatable
    {
        return $this->user ??= $this->check() ? User::find(get_current_user_id()) : null;
    }

    /**
     * Validate user credentials without logging them in.
     *
     * @param array<string, string> $credentials User credentials (username and password)
     * @return bool True if credentials are valid
     */
    public function validate(array $credentials = []): bool
    {
        $user = wp_authenticate($credentials['username'], $credentials['password']);
        $this->lastAttempted = $user instanceof WP_Error ? null : User::find($user->ID);

        return ! ($user instanceof WP_Error);
    }

    /**
     * Attempt to authenticate a user and log them in.
     *
     * @param array<string, string> $credentials User credentials
     * @param bool $remember Whether to "remember" the user
     * @return bool True if authentication successful
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        if ($this->validate($credentials)) {
            $user = $this->lastAttempted;
            if ($user instanceof User) {
                wp_set_auth_cookie($user->ID, $remember, Request::secure());
                do_action('wp_login', $user->user_login, $user->toWpUser());
                $this->setUser($user);

                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to authenticate a user once without logging them in.
     *
     * @param array $credentials User credentials
     * @return bool True if authentication successful
     */
    public function once(array $credentials = []): bool
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    /**
     * Log a user in.
     *
     * @param Authenticatable $user The user to log in
     * @param bool $remember Whether to "remember" the user
     */
    public function login(Authenticatable $user, $remember = false): void
    {
        if ($user instanceof User) {
            wp_set_auth_cookie($user->ID, $remember);
            do_action('wp_login', $user->user_login, $user->toWpUser());
            $this->setUser($user);
            wp_set_current_user($user->ID);
        }
    }

    /**
     * Log a user in by their ID.
     *
     * @param mixed $id User ID
     * @param bool $remember Whether to "remember" the user
     * @return Authenticatable|null The logged in user or null
     */
    public function loginUsingId($id, $remember = false): ?Authenticatable
    {
        if ($user = User::find($id)) {
            $this->login($user, $remember);

            return $user;
        }

        return null;
    }

    /**
     * Log a user in once by their ID without starting a session.
     *
     * @param mixed $id User ID
     * @return bool True if successful
     */
    public function onceUsingId($id): bool
    {
        if ($user = User::find($id)) {
            wp_set_current_user($user->ID);
            $this->setUser($user);

            return true;
        }

        return false;
    }

    /**
     * Check if the user was authenticated via "remember me" cookie.
     *
     * @return bool True if authenticated via remember cookie
     */
    public function viaRemember(): bool
    {
        return Request::hasCookie(Request::secure() ? SECURE_AUTH_COOKIE : AUTH_COOKIE);
    }

    /**
     * Log the user out.
     */
    public function logout(): void
    {
        wp_logout();
        $this->user = null;
    }

    /**
     * Set the current user.
     *
     * @param Authenticatable $user The user to set
     * @return static
     */
    public function setUser(Authenticatable $user): static
    {
        if ($user instanceof User) {
            wp_set_current_user($user->ID);
            $this->user = $user;
        }

        return $this;
    }
}
