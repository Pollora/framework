<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Auth\WordPressGuard;
use Pollora\Models\User;

if (! class_exists('WP_User')) {
    eval('class WP_User { public int $ID = 0; public function __construct($id = 0) { $this->ID = (int) $id; } }');
}

beforeEach(function (): void {
    config([
        'database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_'],
        'colt.connection' => 'wordpress',
    ]);

    $schema = resolve('db')->connection('wordpress')->getSchemaBuilder();
    $schema->create('users', function ($table): void {
        $table->increments('ID');
        $table->string('user_login');
        $table->string('user_pass')->default('');
        $table->string('user_nicename')->default('');
        $table->string('user_email')->default('');
        $table->string('user_url')->default('');
        $table->dateTime('user_registered')->nullable();
        $table->string('user_activation_key')->default('');
        $table->integer('user_status')->default(0);
        $table->string('display_name')->default('');
    });
    $schema->create('usermeta', function ($table): void {
        $table->increments('umeta_id');
        $table->unsignedBigInteger('user_id');
        $table->string('meta_key')->nullable();
        $table->longText('meta_value')->nullable();
    });
    resolve('db')->connection('wordpress')->table('users')->insert(['ID' => 7, 'user_login' => 'ada']);

    $this->current = [];
    $this->actions = [];
    Functions\when('wp_set_current_user')->alias(function (int $id): null {
        $this->current[] = $id;

        return null;
    });
    Functions\when('wp_set_auth_cookie')->alias(function (int $id, bool $remember = false, string|bool $secure = ''): void {
        $this->cookies[] = [$id, $remember];
    });
    Functions\when('do_action')->alias(function (string $hook, mixed ...$args): void {
        $this->actions[] = $hook;
    });
    $this->cookies = [];
    $this->guard = new WordPressGuard;
});

describe('WordPressGuard with a user table', function (): void {
    it('logs a user in by ID, sets the cookie, fires wp_login and makes it the current user', function (): void {
        $user = $this->guard->loginUsingId(7, true);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->ID)->toBe(7)
            ->and($this->cookies)->toBe([[7, true]])
            ->and($this->actions)->toBe(['wp_login'])
            ->and($this->current)->toBe([7, 7]);
    });

    it('logs nobody in for an ID that does not exist', function (): void {
        expect($this->guard->loginUsingId(99))->toBeNull()
            ->and($this->cookies)->toBe([]);
    });

    it('makes a user the current one for the request only, without a cookie', function (): void {
        expect($this->guard->onceUsingId(7))->toBeTrue()
            ->and($this->guard->onceUsingId(99))->toBeFalse()
            ->and($this->current)->toBe([7, 7])
            ->and($this->cookies)->toBe([]);
    });

    it('accepts valid credentials once without logging in', function (): void {
        Functions\when('wp_authenticate')->justReturn((object) ['ID' => 7]);

        expect($this->guard->once(['username' => 'ada', 'password' => 'secret']))->toBeTrue()
            ->and($this->current)->toBe([7])
            ->and($this->cookies)->toBe([]);
    });

    it('refuses credentials WordPress refuses', function (): void {
        Functions\when('wp_authenticate')->justReturn(new WP_Error('invalid_username', 'no'));

        expect($this->guard->once(['username' => 'bob', 'password' => 'x']))->toBeFalse()
            ->and($this->guard->attempt(['username' => 'bob', 'password' => 'x']))->toBeFalse();
    });

    it('attempts a login with valid credentials, setting the cookie with the request security', function (): void {
        Functions\when('wp_authenticate')->justReturn((object) ['ID' => 7]);

        expect($this->guard->attempt(['username' => 'ada', 'password' => 'secret'], true))->toBeTrue()
            ->and($this->cookies)->toBe([[7, true]])
            ->and($this->actions)->toBe(['wp_login'])
            ->and($this->current)->toBe([7]);
    });
});
