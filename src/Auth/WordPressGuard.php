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

class WordPressGuard implements StatefulGuard
{
    use GuardHelpers;

    private ?User $lastAttempted = null;

    public function check(): bool
    {
        return is_user_logged_in();
    }

    public function user(): ?Authenticatable
    {
        User::find(get_current_user_id());

        return $this->user ??= $this->check() ? User::find(get_current_user_id()) : null;
    }

    public function validate(array $credentials = []): bool
    {
        $user = wp_authenticate($credentials['username'], $credentials['password']);
        $this->lastAttempted = $user instanceof WP_Error ? null : User::find($user->ID);

        return ! ($user instanceof WP_Error);
    }

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

    public function once(array $credentials = []): bool
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        if ($user instanceof User) {
            wp_set_auth_cookie($user->ID, $remember);
            do_action('wp_login', $user->user_login, $user->toWpUser());
            $this->setUser($user);
            wp_set_current_user($user->ID);
        }
    }

    public function loginUsingId($id, $remember = false): ?Authenticatable
    {
        if ($user = User::find($id)) {
            $this->login($user, $remember);

            return $user;
        }

        return null;
    }

    public function onceUsingId($id): bool
    {
        if ($user = User::find($id)) {
            wp_set_current_user($user->ID);
            $this->setUser($user);

            return true;
        }

        return false;
    }

    public function viaRemember(): bool
    {
        return Request::hasCookie(Request::secure() ? SECURE_AUTH_COOKIE : AUTH_COOKIE);
    }

    public function logout(): void
    {
        wp_logout();
        $this->user = null;
    }

    public function setUser(Authenticatable $user): static
    {
        if ($user instanceof User) {
            wp_set_current_user($user->ID);
            $this->user = $user;
        }

        return $this;
    }
}
