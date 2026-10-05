<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Models\User;
use Tests\Unit\Role\Fixtures\EventManager;

require_once __DIR__.'/Fixtures/role-users.php';

it("lists the slugs of the user's roles", function (): void {
    $user = userWithWpUser(wpUserWithRoles(['author', 'event_manager']));

    expect($user->roles())->toBe(['author', 'event_manager'])
        ->and($user->roles)->toBe(['author', 'event_manager']);
});

it('tells whether the user has one of the roles, by slug or class', function (): void {
    $user = userWithWpUser(wpUserWithRoles(['event_manager']));

    expect($user->hasRole(EventManager::class))->toBeTrue()
        ->and($user->hasRole('editor', 'event_manager'))->toBeTrue()
        ->and($user->hasRole('editor'))->toBeFalse();
});

it('assigns a role WordPress knows, through WP_User', function (): void {
    $roles = Mockery::mock();
    $roles->shouldReceive('is_role')->once()->with('event_manager')->andReturn(true);
    Functions\when('wp_roles')->justReturn($roles);
    $wpUser = wpUserWithRoles(['subscriber']);

    expect(userWithWpUser($wpUser)->assignRole(EventManager::class))->toBeInstanceOf(User::class)
        ->and($wpUser->roles)->toBe(['subscriber', 'event_manager']);
});

it('refuses to assign a role WordPress does not know', function (): void {
    $roles = Mockery::mock();
    $roles->shouldReceive('is_role')->with('ghost')->andReturn(false);
    Functions\when('wp_roles')->justReturn($roles);
    $wpUser = wpUserWithRoles(['subscriber']);

    expect(fn (): User => userWithWpUser($wpUser)->assignRole('ghost'))->toThrow(InvalidArgumentException::class, 'The role "ghost" does not exist.')
        ->and($wpUser->roles)->toBe(['subscriber']);
});

it('removes a role, by slug or class', function (): void {
    $wpUser = wpUserWithRoles(['subscriber', 'event_manager', 'retired']);
    $user = userWithWpUser($wpUser);

    $user->removeRole(EventManager::class)->removeRole('retired');

    expect($wpUser->roles)->toBe(['subscriber']);
});
