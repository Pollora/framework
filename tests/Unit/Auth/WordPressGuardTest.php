<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\UserProvider;
use Pollora\Auth\WordPressGuard;
use Pollora\Models\User;

beforeEach(function (): void {
    $this->provider = Mockery::mock(UserProvider::class);
    $this->guard = new WordPressGuard($this->provider);
});

describe('WordPressGuard', function (): void {
    it('implements StatefulGuard interface', function (): void {
        expect($this->guard)->toBeInstanceOf(\Illuminate\Contracts\Auth\StatefulGuard::class);
    });

    it('checks if user is logged in via WordPress', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        expect($this->guard->check())->toBeTrue();
    });

    it('returns false when user is not logged in', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $guard = new WordPressGuard($this->provider);
        expect($guard->check())->toBeFalse();
    });

    it('returns null user when not logged in', function (): void {
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $guard = new WordPressGuard($this->provider);
        expect($guard->user())->toBeNull();
    });

    it('returns false on invalid credentials', function (): void {
        $error = Mockery::mock(\WP_Error::class);
        Brain\Monkey\Functions\when('wp_authenticate')->justReturn($error);

        expect($this->guard->validate(['username' => 'bad', 'password' => 'bad']))->toBeFalse();
    });

    it('logs out via WordPress and clears user', function (): void {
        Brain\Monkey\Functions\expect('wp_logout')->once();

        $this->guard->logout();

        // After logout, internal user should be cleared
        Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
        expect($this->guard->user())->toBeNull();
    });

    it('sets user and syncs with WordPress wp_set_current_user', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->ID = 42;

        Brain\Monkey\Functions\expect('wp_set_current_user')->once()->with(42);

        $result = $this->guard->setUser($user);

        expect($result)->toBe($this->guard);
    });

    it('ignores non-User authenticatable in setUser', function (): void {
        $user = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);

        // Should not call wp_set_current_user for non-User objects
        $result = $this->guard->setUser($user);

        expect($result)->toBe($this->guard);
    });
});
