<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Pollora\Auth\WordPressGuard;
use Pollora\Models\User;

// WordPress's session store, declared only for these tests when WordPress is absent.
if (! class_exists('WP_Session_Tokens')) {
    eval('class WP_Session_Tokens {
        public static array $sessions = [];
        public static function get_instance($userId): self { return new self; }
        public function get($token) { return self::$sessions[$token] ?? null; }
    }');
}

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

    it('returns false, without reading the credentials, when the username or the password is missing', function (array $credentials): void {
        Brain\Monkey\Functions\expect('wp_authenticate')->never();

        expect($this->guard->validate($credentials))->toBeFalse();
    })->with([
        'nothing' => [[]],
        'no password' => [['username' => 'a']],
        'no username' => [['password' => 'b']],
        'null password' => [['username' => 'a', 'password' => null]],
    ]);

    it('does not attempt a login without credentials', function (): void {
        Brain\Monkey\Functions\expect('wp_authenticate')->never();

        expect($this->guard->attempt([]))->toBeFalse()
            ->and($this->guard->once([]))->toBeFalse();
    });

    describe('viaRemember', function (): void {
        beforeEach(function (): void {
            WP_Session_Tokens::$sessions = [];
            Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
            Brain\Monkey\Functions\when('get_current_user_id')->justReturn(7);
            Brain\Monkey\Functions\when('wp_get_session_token')->justReturn('tok');
            Brain\Monkey\Functions\when('apply_filters')->alias(fn (string $hook, $value) => $value);
        });

        it('is false for an authentication cookie without "remember me" (a two day session)', function (): void {
            WP_Session_Tokens::$sessions['tok'] = ['login' => 1000, 'expiration' => 1000 + 172800];

            expect($this->guard->viaRemember())->toBeFalse();
        });

        it('is true for a session that lives longer than two days (14 days)', function (): void {
            WP_Session_Tokens::$sessions['tok'] = ['login' => 1000, 'expiration' => 1000 + 14 * 86400];

            expect($this->guard->viaRemember())->toBeTrue();
        });

        it('compares with the lifetime the auth_cookie_expiration filter gives a session without "remember me"', function (): void {
            Brain\Monkey\Functions\when('apply_filters')->alias(fn (string $hook, $value, $userId, $remember) => $remember ? $value : 30 * 86400);
            WP_Session_Tokens::$sessions['tok'] = ['login' => 1000, 'expiration' => 1000 + 14 * 86400];

            expect($this->guard->viaRemember())->toBeFalse();
        });

        it('is false when nobody is logged in', function (): void {
            Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);
            WP_Session_Tokens::$sessions['tok'] = ['login' => 1000, 'expiration' => 1000 + 14 * 86400];

            expect($this->guard->viaRemember())->toBeFalse();
        });

        it('is false when the logged-in cookie carries no session token', function (): void {
            Brain\Monkey\Functions\when('wp_get_session_token')->justReturn('');
            WP_Session_Tokens::$sessions[''] = ['login' => 1000, 'expiration' => 1000 + 14 * 86400];

            expect($this->guard->viaRemember())->toBeFalse();
        });

        it('is false when there is no current user id', function (): void {
            Brain\Monkey\Functions\when('get_current_user_id')->justReturn(0);
            WP_Session_Tokens::$sessions['tok'] = ['login' => 1000, 'expiration' => 1000 + 14 * 86400];

            expect($this->guard->viaRemember())->toBeFalse();
        });

        it('is false when the session is unknown or incomplete', function (array $session): void {
            WP_Session_Tokens::$sessions['tok'] = $session;

            expect($this->guard->viaRemember())->toBeFalse();
        })->with([
            'no expiration' => [['login' => 1000]],
            'no login' => [['expiration' => 99999999]],
        ]);

        it('is false when the session token is not known', function (): void {
            expect($this->guard->viaRemember())->toBeFalse();
        });
    });
});
