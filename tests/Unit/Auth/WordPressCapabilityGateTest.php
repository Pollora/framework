<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Pollora\Auth\WordPressCapabilityGate;

if (! class_exists('WP_User')) {
    eval('class WP_User { public int $ID = 0; }');
}

/**
 * A Laravel Gate for the given user, answering with WordPress capabilities.
 */
function wordPressGate(mixed $user): Gate
{
    $gate = new Gate(new Container, fn (): mixed => $user);
    $gate->after((new WordPressCapabilityGate)->__invoke(...));

    return $gate;
}

function authenticatableUser(int $id): Authenticatable
{
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($id);

    return $user;
}

it('answers with user_can() for the authenticated user ID', function (): void {
    Functions\expect('user_can')->once()->with(1, 'edit_posts')->andReturn(true);

    expect(wordPressGate(authenticatableUser(1))->allows('edit_posts'))->toBeTrue();
});

it('denies what WordPress denies', function (): void {
    Functions\expect('user_can')->once()->with(7, 'manage_options')->andReturn(false);

    expect(wordPressGate(authenticatableUser(7))->allows('manage_options'))->toBeFalse();
});

it('passes Eloquent model arguments as their key', function (): void {
    $post = new class extends Model
    {
        protected $primaryKey = 'ID';
    };
    $post->ID = 42;
    Functions\expect('user_can')->once()->with(1, 'edit_post', 42)->andReturn(true);

    expect(wordPressGate(authenticatableUser(1))->allows('edit_post', $post))->toBeTrue();
});

it('passes a WP_User and other arguments unchanged', function (): void {
    $wpUser = new WP_User;
    Functions\expect('user_can')->once()->with($wpUser, 'edit_post', 42)->andReturn(true);

    expect(wordPressGate($wpUser)->allows('edit_post', [42]))->toBeTrue();
});

it('accepts a user ID', function (): void {
    Functions\expect('user_can')->once()->with(3, 'read')->andReturn(true);

    expect(wordPressGate(3)->allows('read'))->toBeTrue();
});

it('leaves a guest to the Gate default', function (): void {
    Functions\expect('user_can')->never();

    expect(wordPressGate(null)->allows('read'))->toBeFalse();
});

it('leaves an unknown kind of user undecided', function (): void {
    Functions\expect('user_can')->never();

    expect((new WordPressCapabilityGate)(new stdClass, 'read', null, []))->toBeNull();
});

it('lets abilities defined on the Gate take priority', function (): void {
    Functions\when('user_can')->justReturn(false);
    $gate = wordPressGate(authenticatableUser(1));
    $gate->define('export_attendees', fn (): bool => true);

    expect($gate->allows('export_attendees'))->toBeTrue();
});
