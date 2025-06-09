<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\User;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_User;

/**
 * Event dispatcher for WordPress user-related events.
 *
 * This class handles the dispatching of Laravel events for WordPress user actions
 * such as registration, profile updates, authentication, and role changes.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'user_register',
        'profile_update',
        'password_reset',
        'retrieve_password',
        'set_logged_in_cookie',
        'clear_auth_cookie',
        'delete_user',
        'deleted_user',
        'set_user_role',
    ];

    /**
     * Store users before deletion to preserve their data for the deleted_user event.
     *
     * @var array<int, WP_User>
     */
    private array $usersToDelete = [];

    /**
     * Handle user registration.
     *
     * @param  int  $user_id  User ID
     */
    public function handleUserRegister(int $user_id): void
    {
        $user = get_user_by('id', $user_id);
        $creator = wp_get_current_user();

        if (! $user) {
            return;
        }

        $this->dispatch(UserCreated::class, [
            $user,
            $creator->exists() ? $creator : null,
        ]);
    }

    /**
     * Handle profile update.
     *
     * @param  int  $user_id  User ID
     * @param  WP_User  $user  User object
     */
    public function handleProfileUpdate(int $user_id, WP_User $user): void
    {
        $this->dispatch(UserUpdated::class, [$user]);
    }

    /**
     * Handle password reset.
     *
     * @param  WP_User  $user  User object
     */
    public function handlePasswordReset(WP_User $user): void
    {
        $this->dispatch(UserPasswordReset::class, [$user]);
    }

    /**
     * Handle password reset request.
     *
     * @param  string  $user_login  Username or email
     */
    public function handleRetrievePassword(string $user_login): void
    {
        $user = is_email($user_login)
            ? get_user_by('email', $user_login)
            : get_user_by('login', $user_login);

        if (! $user) {
            return;
        }

        $this->dispatch(UserPasswordRequested::class, [$user]);
    }

    /**
     * Handle user login.
     *
     * @param  string  $cookie  Auth cookie
     * @param  int  $expire  Cookie expiration
     * @param  int  $expiration  Cookie expiration timestamp
     * @param  int  $user_id  User ID
     */
    public function handleSetLoggedInCookie(string $cookie, int $expire, int $expiration, int $user_id): void
    {
        $user = get_user_by('id', $user_id);

        if (! $user) {
            return;
        }

        $this->dispatch(UserLoggedIn::class, [$user]);
    }

    /**
     * Handle user logout.
     */
    public function handleClearAuthCookie(): void
    {
        $user = wp_get_current_user();

        if (! $user->exists()) {
            return;
        }

        $this->dispatch(UserLoggedOut::class, [$user]);
    }

    /**
     * Store user before deletion.
     *
     * @param  int  $user_id  User ID
     */
    public function handleDeleteUser(int $user_id): void
    {
        $user = get_user_by('id', $user_id);

        if (! $user) {
            return;
        }

        $this->usersToDelete[$user_id] = $user;
    }

    /**
     * Handle user deletion.
     *
     * @param  int  $user_id  User ID
     */
    public function handleDeletedUser(int $user_id): void
    {
        if (! isset($this->usersToDelete[$user_id])) {
            return;
        }

        $user = $this->usersToDelete[$user_id];
        unset($this->usersToDelete[$user_id]);

        $roles = array_map(
            fn (string $role): string => wp_roles()->get_role($role)->name,
            $user->roles
        );

        $this->dispatch(UserDeleted::class, [$user, $roles]);
    }

    /**
     * Handle role change.
     *
     * @param  int  $user_id  User ID
     * @param  string|null  $role  New role
     * @param  array  $old_roles  Previous roles
     */
    public function handleSetUserRole(int $user_id, ?string $role, array $old_roles): void
    {
        if ($old_roles === []) {
            return;
        }

        $user = get_user_by('id', $user_id);

        if (! $user) {
            return;
        }

        $this->dispatch(UserRoleChanged::class, [$user, $old_roles, $role]);
    }
}
