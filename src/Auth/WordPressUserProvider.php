<?php

declare(strict_types=1);

namespace Pollen\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Pollen\Models\User;
use WP_Error;

class WordPressUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // WordPress n'utilise pas de token de rappel par défaut
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // WordPress n'utilise pas de token de rappel par défaut
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $user = wp_authenticate($credentials['username'], $credentials['password']);
        return $user instanceof WP_Error ? null : User::find($user->ID);
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return wp_check_password($credentials['password'], $user->user_pass, $user->ID);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): bool
    {
        // WordPress gère automatiquement le rehashage des mots de passe
        return false;
    }
}
