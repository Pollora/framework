<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Pollora\Auth\WordPressGuard;
use Pollora\Models\User;

beforeEach(function (): void {
    $this->provider = Mockery::mock(UserProvider::class);
    $this->guard = new WordPressGuard($this->provider);
});

describe('WordPressGuard', function (): void {
    it('implements StatefulGuard interface', function (): void {
        expect($this->guard)->toBeInstanceOf(StatefulGuard::class);
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
        $error = Mockery::mock(WP_Error::class);
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
        $user = Mockery::mock(Authenticatable::class);

        // Should not call wp_set_current_user for non-User objects
        $result = $this->guard->setUser($user);

        expect($result)->toBe($this->guard);
    });

    it('attempt returns false on invalid credentials', function (): void {
        $error = Mockery::mock(WP_Error::class);
        Brain\Monkey\Functions\when('wp_authenticate')->justReturn($error);

        expect($this->guard->attempt(['username' => 'bad', 'password' => 'bad']))->toBeFalse();
    });

    it('once returns false on invalid credentials', function (): void {
        $error = Mockery::mock(WP_Error::class);
        Brain\Monkey\Functions\when('wp_authenticate')->justReturn($error);

        expect($this->guard->once(['username' => 'bad', 'password' => 'bad']))->toBeFalse();
    });

    it('login calls wp_set_auth_cookie for User instances', function (): void {
        if (! class_exists('WP_User')) {
            eval('class WP_User { public int $ID = 0; }');
        }
        $wpUser = new WP_User;
        $wpUser->ID = 7;

        $user = Mockery::mock(User::class)->makePartial();
        $user->ID = 7;
        $user->user_login = 'admin';
        $user->shouldReceive('toWpUser')->andReturn($wpUser);

        Brain\Monkey\Functions\expect('wp_set_auth_cookie')->once()->with(7, true);
        Brain\Monkey\Functions\expect('wp_set_current_user')->atLeast()->once()->with(7);

        $this->guard->login($user, true);
    });

    it('login ignores non-User authenticatable', function (): void {
        $user = Mockery::mock(Authenticatable::class);

        // Should not call any WordPress functions
        $this->guard->login($user);

        expect(true)->toBeTrue();
    });

    it('can be constructed without provider', function (): void {
        $guard = new WordPressGuard;

        expect($guard)->toBeInstanceOf(StatefulGuard::class);
    });
});
