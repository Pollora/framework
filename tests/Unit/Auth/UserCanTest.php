<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Pollora\Models\User;

afterEach(function (): void {
    Container::getInstance()->forgetInstance(Gate::class);
});

it('asks the Gate for the user, with the ability and its arguments', function (): void {
    $user = new User;
    $forUser = Mockery::mock(Gate::class);
    $forUser->shouldReceive('check')->once()->with('edit_post', [42])->andReturn(true);
    $gate = Mockery::mock(Gate::class);
    $gate->shouldReceive('forUser')->once()->with($user)->andReturn($forUser);
    Container::getInstance()->instance(Gate::class, $gate);

    expect($user)->toBeInstanceOf(Authorizable::class)
        ->and($user->can('edit_post', [42]))->toBeTrue();
});
