<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Pollora\Auth\WordPressUserProvider;
use Pollora\Models\User;

beforeEach(function (): void {
    $this->provider = new WordPressUserProvider;
});

describe('WordPressUserProvider', function (): void {
    it('implements UserProvider interface', function (): void {
        expect($this->provider)->toBeInstanceOf(UserProvider::class);
    });

    it('always returns null for retrieveByToken (WordPress does not use remember tokens)', function (): void {
        expect($this->provider->retrieveByToken(1, 'some-token'))->toBeNull();
    });

    it('updateRememberToken is a no-op', function (): void {
        $user = Mockery::mock(Authenticatable::class);

        // Should not throw
        $this->provider->updateRememberToken($user, 'token');

        expect(true)->toBeTrue();
    });

    it('returns null when wp_authenticate returns WP_Error', function (): void {
        $error = Mockery::mock(WP_Error::class);
        Brain\Monkey\Functions\when('wp_authenticate')->justReturn($error);

        expect($this->provider->retrieveByCredentials([
            'username' => 'bad',
            'password' => 'wrong',
        ]))->toBeNull();
    });

    it('validates credentials using wp_check_password', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->ID = 1;
        $user->user_pass = '$hashed_password';

        Brain\Monkey\Functions\when('wp_check_password')->justReturn(true);

        expect($this->provider->validateCredentials($user, ['password' => 'correct']))->toBeTrue();
    });

    it('rejects invalid password via wp_check_password', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->ID = 1;
        $user->user_pass = '$hashed_password';

        Brain\Monkey\Functions\when('wp_check_password')->justReturn(false);

        expect($this->provider->validateCredentials($user, ['password' => 'wrong']))->toBeFalse();
    });

    it('rejects non-User authenticatable in validateCredentials', function (): void {
        $user = Mockery::mock(Authenticatable::class);

        expect($this->provider->validateCredentials($user, ['password' => 'x']))->toBeFalse();
    });

    it('returns false for rehashPasswordIfRequired', function (): void {
        $user = Mockery::mock(Authenticatable::class);

        expect($this->provider->rehashPasswordIfRequired($user, ['password' => 'x']))->toBeFalse();
    });
});
