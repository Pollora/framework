<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Request;
use Pollora\Models\User;
use WP_Error;
use WP_Session_Tokens;

/**
 * WordPress authentication guard for Laravel.
 *
 * This class implements Laravel's StatefulGuard interface to provide WordPress
 * authentication functionality within Laravel's authentication system.
 * It handles user authentication, session management, and WordPress-specific
 * authentication features.
 *
 * @uses GuardHelpers
 */
class WordPressGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * WordPress's lifetime of a session without "remember me": two days, in seconds.
     */
    private const int SESSION_LIFETIME = 172800;

    /**
     * The last attempted user authentication.
     */
    private ?User $lastAttempted = null;

    /**
     * Create a new WordPress guard instance.
     *
     * @param  UserProvider|null  $provider  The user provider
     */
    public function __construct(?UserProvider $provider = null)
    {
        $this->provider = $provider;
    }

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
     * @param  array<string, string>  $credentials  User credentials (username and password)
     * @return bool True if credentials are valid; false too when the username or the password is missing
     */
    public function validate(array $credentials = []): bool
    {
        if (! isset($credentials['username'], $credentials['password'])) {
            return false;
        }

        $user = wp_authenticate($credentials['username'], $credentials['password']);
        $this->lastAttempted = $user instanceof WP_Error ? null : User::find($user->ID);

        return ! ($user instanceof WP_Error);
    }

    /**
     * Attempt to authenticate a user and log them in.
     *
     * @param  array<string, string>  $credentials  User credentials
     * @param  bool  $remember  Whether to "remember" the user
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
     * @param  array  $credentials  User credentials
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
     * @param  Authenticatable  $user  The user to log in
     * @param  bool  $remember  Whether to "remember" the user
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
     * @param  mixed  $id  User ID
     * @param  bool  $remember  Whether to "remember" the user
     * @return Authenticatable|null The logged-in user or null
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
     * @param  mixed  $id  User ID
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
     * Check if the current session was opened with "remember me".
     *
     * WordPress keeps no "remember" flag: the choice only sets how long the session
     * lives (14 days instead of 2, through the `auth_cookie_expiration` filter) and
     * whether the cookie outlives the browser. The authentication cookie being
     * present therefore proves nothing; what tells is the lifetime of the session
     * token the logged-in cookie carries, compared with the lifetime a session
     * without "remember me" gets. When that cannot be read (no token, no session, a
     * site whose filter gives both the same lifetime) the answer is false.
     *
     * @return bool True if the session lives longer than a session without "remember me"
     */
    public function viaRemember(): bool
    {
        if (! $this->check()) {
            return false;
        }

        $token = wp_get_session_token();
        $userId = get_current_user_id();

        if ($token === '' || $userId === 0) {
            return false;
        }

        $session = WP_Session_Tokens::get_instance($userId)->get($token);

        if (! is_array($session) || ! isset($session['expiration'], $session['login'])) {
            return false;
        }

        $lifetime = (int) $session['expiration'] - (int) $session['login'];
        $withoutRemember = (int) apply_filters('auth_cookie_expiration', self::SESSION_LIFETIME, $userId, false);

        return $lifetime > $withoutRemember;
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
     * @param  Authenticatable  $user  The user to set
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
