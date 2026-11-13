<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Events\WordPress\Plugins\UserSwitching\UserSwitchedBack;
use Pollora\Events\WordPress\Plugins\UserSwitching\UserSwitchedOff;
use Pollora\Events\WordPress\Plugins\UserSwitching\UserSwitchedTo;
use Pollora\Events\WordPress\Plugins\UserSwitching\UserSwitchingEventDispatcher;
use Pollora\Events\WordPress\User\UserCreated;
use Pollora\Events\WordPress\User\UserDeleted;
use Pollora\Events\WordPress\User\UserEventDispatcher;
use Pollora\Events\WordPress\User\UserLoggedIn;
use Pollora\Events\WordPress\User\UserLoggedOut;
use Pollora\Events\WordPress\User\UserPasswordRequested;
use Pollora\Events\WordPress\User\UserPasswordReset;
use Pollora\Events\WordPress\User\UserRoleChanged;
use Pollora\Events\WordPress\User\UserUpdated;
use Tests\Unit\Events\Fixtures\FakeUser;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

describe('UserEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(UserEventDispatcher::class);
        $this->user = new FakeUser(7);

        Functions\when('get_user_by')->alias(fn (string $field, mixed $value): ?object => match ([$field, $value]) {
            ['id', 7], ['email', 'jane@example.com'], ['login', 'jane'] => $this->user,
            default => null,
        });
        Functions\when('is_email')->alias(fn (string $value): bool => str_contains($value, '@'));
    });

    it('dispatches UserCreated with the creator when an admin registered the user', function (): void {
        $admin = new FakeUser(1);
        Functions\when('wp_get_current_user')->justReturn($admin);

        $this->dispatcher->handleUserRegister(7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserCreated::class)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->creator)->toBe($admin);
    });

    it('dispatches UserCreated without a creator for a self-registration', function (): void {
        Functions\when('wp_get_current_user')->justReturn(new FakeUser(0, [], false));

        $this->dispatcher->handleUserRegister(7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->creator)->toBeNull();
    });

    it('ignores the registration of a user that cannot be loaded', function (): void {
        Functions\when('wp_get_current_user')->justReturn(new FakeUser(1));

        $this->dispatcher->handleUserRegister(404);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches UserUpdated with the updated user object', function (): void {
        $this->dispatcher->handleProfileUpdate(7, $this->user);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserUpdated::class)
            ->and($this->log[0]->user)->toBe($this->user);
    });

    it('dispatches UserPasswordReset with the user whose password was reset', function (): void {
        $this->dispatcher->handlePasswordReset($this->user);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserPasswordReset::class)
            ->and($this->log[0]->user)->toBe($this->user);
    });

    it('finds the user who asked for a new password by e-mail or by login', function (string $identifier): void {
        $this->dispatcher->handleRetrievePassword($identifier);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserPasswordRequested::class)
            ->and($this->log[0]->user)->toBe($this->user);
    })->with(['jane@example.com', 'jane']);

    it('ignores a password request for an unknown user', function (string $identifier): void {
        $this->dispatcher->handleRetrievePassword($identifier);

        expect($this->log)->toHaveCount(0);
    })->with(['nobody@example.com', 'nobody']);

    it('dispatches UserLoggedIn for the user of the cookie', function (): void {
        $this->dispatcher->handleSetLoggedInCookie('cookie', 100, 200, 7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserLoggedIn::class)
            ->and($this->log[0]->user)->toBe($this->user);
    });

    it('ignores a login cookie of an unknown user', function (): void {
        $this->dispatcher->handleSetLoggedInCookie('cookie', 100, 200, 404);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches UserLoggedOut for the current user', function (): void {
        Functions\when('wp_get_current_user')->justReturn($this->user);

        $this->dispatcher->handleClearAuthCookie();

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserLoggedOut::class)
            ->and($this->log[0]->user)->toBe($this->user);
    });

    it('ignores the clearing of cookies when nobody is logged in', function (): void {
        Functions\when('wp_get_current_user')->justReturn(new FakeUser(0, [], false));

        $this->dispatcher->handleClearAuthCookie();

        expect($this->log)->toHaveCount(0);
    });

    it('reports a deleted user with the names of the roles they had', function (): void {
        $user = new FakeUser(7, ['editor', 'author']);
        Functions\when('get_user_by')->justReturn($user);
        Functions\when('wp_roles')->justReturn(new class
        {
            public function get_role(string $role): stdClass
            {
                return (object) ['name' => ucfirst($role).' role'];
            }
        });

        $this->dispatcher->handleDeleteUser(7);
        expect($this->log)->toHaveCount(0);

        $this->dispatcher->handleDeletedUser(7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserDeleted::class)
            ->and($this->log[0]->user)->toBe($user)
            ->and($this->log[0]->roles)->toBe(['Editor role', 'Author role']);
    });

    it('reports a deletion only once', function (): void {
        Functions\when('wp_roles')->justReturn(new class
        {
            public function get_role(string $role): stdClass
            {
                return (object) ['name' => $role];
            }
        });

        $this->dispatcher->handleDeleteUser(7);
        $this->dispatcher->handleDeletedUser(7);
        $this->dispatcher->handleDeletedUser(7);

        expect($this->log)->toHaveCount(1);
    });

    it('ignores a deletion that was not announced beforehand', function (): void {
        $this->dispatcher->handleDeletedUser(7);
        $this->dispatcher->handleDeleteUser(404);
        $this->dispatcher->handleDeletedUser(404);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches UserRoleChanged with the previous roles and the new one', function (): void {
        $this->dispatcher->handleSetUserRole(7, 'editor', ['subscriber']);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserRoleChanged::class)
            ->and($this->log[0]->user)->toBe($this->user)
            ->and($this->log[0]->oldRoles)->toBe(['subscriber'])
            ->and($this->log[0]->newRole)->toBe('editor');
    });

    it('does not report a role change for a user who had no role before', function (): void {
        $this->dispatcher->handleSetUserRole(7, 'subscriber', []);

        expect($this->log)->toHaveCount(0);
    });

    it('does not report a role change for an unknown user', function (): void {
        $this->dispatcher->handleSetUserRole(404, 'editor', ['subscriber']);

        expect($this->log)->toHaveCount(0);
    });

    it('reports the removal of every role with a null new role', function (): void {
        $this->dispatcher->handleSetUserRole(7, null, ['subscriber']);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->newRole)->toBeNull();
    });
});

describe('UserSwitchingEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(UserSwitchingEventDispatcher::class);
        $this->target = new FakeUser(2);
        $this->origin = new FakeUser(1);

        Functions\when('get_user_by')->alias(fn (string $field, int $id): ?object => $field === 'id' ? [1 => $this->origin, 2 => $this->target][$id] ?? null : null);
    });

    it('dispatches UserSwitchedTo with the new user and the one who switched', function (): void {
        $this->dispatcher->handleSwitchToUser(2, 1);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserSwitchedTo::class)
            ->and($this->log[0]->user)->toBe($this->target)
            ->and($this->log[0]->oldUser)->toBe($this->origin);
    });

    it('ignores a switch when either user is unknown', function (int $userId, int $oldUserId): void {
        $this->dispatcher->handleSwitchToUser($userId, $oldUserId);

        expect($this->log)->toHaveCount(0);
    })->with([
        'unknown target' => [404, 1],
        'unknown origin' => [2, 404],
    ]);

    it('dispatches UserSwitchedBack with the user switched back to and the one left', function (): void {
        $this->dispatcher->handleSwitchBackUser(1, 2);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserSwitchedBack::class)
            ->and($this->log[0]->user)->toBe($this->origin)
            ->and($this->log[0]->oldUser)->toBe($this->target);
    });

    it('dispatches UserSwitchedBack without an old user when WordPress gives none', function (): void {
        $this->dispatcher->handleSwitchBackUser(1, false);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->user)->toBe($this->origin)
            ->and($this->log[0]->oldUser)->toBeNull();
    });

    it('ignores a switch back to an unknown user', function (): void {
        $this->dispatcher->handleSwitchBackUser(404, 2);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches UserSwitchedOff with the user who switched off', function (): void {
        $this->dispatcher->handleSwitchOffUser(2);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(UserSwitchedOff::class)
            ->and($this->log[0]->user)->toBe($this->target);
    });

    it('ignores a switch off of an unknown user', function (): void {
        $this->dispatcher->handleSwitchOffUser(404);

        expect($this->log)->toHaveCount(0);
    });
});
