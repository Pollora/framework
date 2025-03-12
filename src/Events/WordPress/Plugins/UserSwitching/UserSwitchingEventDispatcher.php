<?php

declare(strict_types=1);

namespace Pollora\Events\WordPress\Plugins\UserSwitching;

use Pollora\Events\WordPress\AbstractEventDispatcher;

/**
 * Event dispatcher for User Switching plugin events.
 *
 * This class handles the dispatching of Laravel events for actions performed
 * by the User Switching plugin, such as switching between users, switching back,
 * and switching off.
 *
 * @author Olivier Gorzalka <olivier@amphibee.fr>
 */
class UserSwitchingEventDispatcher extends AbstractEventDispatcher
{
    /**
     * WordPress actions to listen to.
     *
     * @var array<string>
     */
    protected array $actions = [
        'switch_to_user',
        'switch_back_user',
        'switch_off_user',
    ];

    /**
     * Handle user switch event.
     *
     * @param  int  $userId  The ID of the user being switched to
     * @param  int  $oldUserId  The ID of the user being switched from
     */
    public function handleSwitchToUser(int $userId, int $oldUserId): void
    {
        $user = get_user_by('id', $userId);
        $oldUser = get_user_by('id', $oldUserId);

        if (! $user || ! $oldUser) {
            return;
        }

        $this->dispatch(UserSwitchedTo::class, [$user, $oldUser]);
    }

    /**
     * Handle user switch back event.
     *
     * @param  int  $userId  The ID of the user being switched back to
     * @param  int|false  $oldUserId  The ID of the user being switched from, or false if switching back after being switched off
     */
    public function handleSwitchBackUser(int $userId, int|false $oldUserId): void
    {
        $user = get_user_by('id', $userId);
        $oldUser = $oldUserId ? get_user_by('id', $oldUserId) : null;

        if (! $user) {
            return;
        }

        $this->dispatch(UserSwitchedBack::class, [$user, $oldUser]);
    }

    /**
     * Handle user switch off event.
     *
     * @param  int  $oldUserId  The ID of the user switching off
     */
    public function handleSwitchOffUser(int $oldUserId): void
    {
        $oldUser = get_user_by('id', $oldUserId);

        if (! $oldUser) {
            return;
        }

        $this->dispatch(UserSwitchedOff::class, [$oldUser]);
    }
}
