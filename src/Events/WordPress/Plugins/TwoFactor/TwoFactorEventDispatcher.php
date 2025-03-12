<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\TwoFactor;

use Pollora\Events\WordPress\AbstractEventDispatcher;
use WP_Error;
use WP_User;

/**
 * Event dispatcher for Two Factor authentication related events.
 *
 * This class handles the dispatching of Laravel events for Two Factor actions
 * such as successful authentication, failed attempts, and configuration changes.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class TwoFactorEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'two_factor_user_authenticated',
        'wp_login_failed',
        'update_user_meta',
        'updated_user_meta',
        'added_user_meta',
    ];

    /**
     * Store user meta values before updates.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $userMeta = [];

    /**
     * Handle successful Two Factor authentication.
     *
     * @param  WP_User  $user  Authenticated user
     * @param  ?object  $provider  The Two Factor provider used
     */
    public function handleTwoFactorUserAuthenticated(WP_User $user, ?object $provider = null): void
    {
        $providerKey = $provider ? $provider->get_key() : 'unknown';

        $this->dispatch(TwoFactorAuthenticated::class, [$user, $providerKey]);
    }

    /**
     * Handle failed login attempts with Two Factor errors.
     *
     * @param  string  $userLogin  Username or email
     * @param  WP_Error  $error  Error object
     */
    public function handleWpLoginFailed(string $userLogin, WP_Error $error): void
    {
        if (! str_starts_with($error->get_error_code(), 'two_factor_')) {
            return;
        }

        $user = get_user_by('login', $userLogin) ?: (is_email($userLogin) ? get_user_by('email', $userLogin) : null);

        if (! $user) {
            return;
        }

        $this->dispatch(TwoFactorAuthenticationFailed::class, [
            $user,
            $error->get_error_code(),
            $error->get_error_message(),
        ]);
    }

    /**
     * Store user meta before update.
     *
     * @param  array|int  $metaIds  Array of metadata entry IDs or single ID
     * @param  int  $userId  User ID
     * @param  string  $metaKey  Meta key
     * @param  mixed  $metaValue  Meta value
     */
    public function handleUpdateUserMeta($metaIds, int $userId, string $metaKey, mixed $metaValue): void
    {
        if (! $this->isTwoFactorMeta($metaKey)) {
            return;
        }

        $this->userMeta[$userId][$metaKey] = get_user_meta($userId, $metaKey, true);
    }

    /**
     * Handle user meta updates.
     *
     * @param  array|int  $metaIds  Array of metadata entry IDs or single ID
     * @param  int  $userId  User ID
     * @param  string  $metaKey  Meta key
     * @param  mixed  $metaValue  Meta value
     */
    public function handleUpdatedUserMeta($metaIds, int $userId, string $metaKey, mixed $metaValue): void
    {
        if (! $this->isTwoFactorMeta($metaKey)) {
            return;
        }

        $user = get_user_by('ID', $userId);
        if (! $user) {
            return;
        }

        $oldValue = $this->userMeta[$userId][$metaKey] ?? null;
        unset($this->userMeta[$userId][$metaKey]);

        $this->handleTwoFactorMetaChange($user, $metaKey, $oldValue, $metaValue);
    }

    /**
     * Handle new user meta.
     *
     * @param  array|int  $metaIds  Array of metadata entry IDs or single ID
     * @param  int  $userId  User ID
     * @param  string  $metaKey  Meta key
     * @param  mixed  $metaValue  Meta value
     */
    public function handleAddedUserMeta($metaIds, int $userId, string $metaKey, mixed $metaValue): void
    {
        if (! $this->isTwoFactorMeta($metaKey)) {
            return;
        }

        $user = get_user_by('ID', $userId);
        if (! $user) {
            return;
        }

        $this->handleTwoFactorMetaChange($user, $metaKey, null, $metaValue);
    }

    /**
     * Handle Two Factor meta changes.
     *
     * @param  WP_User  $user  User object
     * @param  string  $metaKey  Meta key
     * @param  mixed  $oldValue  Old value
     * @param  mixed  $newValue  New value
     */
    protected function handleTwoFactorMetaChange(WP_User $user, string $metaKey, mixed $oldValue, mixed $newValue): void
    {
        $action = match ($metaKey) {
            '_two_factor_backup_codes' => 'backup_codes',
            '_two_factor_totp_key' => 'totp_key',
            '_two_factor_enabled_providers' => 'providers',
            default => null,
        };

        if (! $action) {
            return;
        }

        if ($action === 'providers') {
            $oldProviders = (array) $oldValue;
            $newProviders = (array) $newValue;

            $enabledProviders = array_diff($newProviders, $oldProviders);
            $disabledProviders = array_diff($oldProviders, $newProviders);

            foreach ($enabledProviders as $provider) {
                $this->dispatch(TwoFactorConfigurationChanged::class, [
                    $user,
                    'enabled',
                    $provider,
                    $oldProviders,
                    $newProviders,
                ]);
            }

            foreach ($disabledProviders as $provider) {
                $this->dispatch(TwoFactorConfigurationChanged::class, [
                    $user,
                    'disabled',
                    $provider,
                    $oldProviders,
                    $newProviders,
                ]);
            }
        } else {
            $this->dispatch(TwoFactorConfigurationChanged::class, [
                $user,
                'updated',
                $action,
                $oldValue ? [$oldValue] : null,
                $newValue ? [$newValue] : null,
            ]);
        }
    }

    /**
     * Check if meta key is related to Two Factor.
     *
     * @param  string  $metaKey  Meta key to check
     */
    protected function isTwoFactorMeta(string $metaKey): bool
    {
        return in_array($metaKey, [
            '_two_factor_backup_codes',
            '_two_factor_totp_key',
            '_two_factor_enabled_providers',
        ], true);
    }
}
