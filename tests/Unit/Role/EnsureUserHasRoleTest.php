<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Pollora\Role\Infrastructure\Middleware\EnsureUserHasRole;
use Symfony\Component\HttpFoundation\Response;
use Tests\Unit\Role\Fixtures\EventManager;

require_once __DIR__.'/Fixtures/role-users.php';

/**
 * Runs the middleware for a request made by the given user.
 */
function throughRoleMiddleware(mixed $user, string ...$roles): Response
{
    $request = Request::create('/events');
    $request->setUserResolver(fn (): mixed => $user);

    return (new EnsureUserHasRole)->handle($request, fn (): Response => new Response('passed'), ...$roles);
}

it('lets through a user who has one of the roles', function (): void {
    $user = userWithWpUser(wpUserWithRoles(['event_manager']));

    expect(throughRoleMiddleware($user, 'editor', EventManager::class)->getContent())->toBe('passed');
});

it('refuses a user who has none of the roles', function (): void {
    throughRoleMiddleware(userWithWpUser(wpUserWithRoles(['subscriber'])), 'editor');
})->throws(AuthorizationException::class);

it('refuses a guest', function (): void {
    throughRoleMiddleware(null, 'editor');
})->throws(AuthorizationException::class);

it('refuses a user model without roles', function (): void {
    throughRoleMiddleware(Mockery::mock(Authenticatable::class), 'editor');
})->throws(AuthorizationException::class);

it('builds the middleware string for slugs and role classes', function (): void {
    expect(EnsureUserHasRole::using('editor', EventManager::class))
        ->toBe(EnsureUserHasRole::class.':editor,'.EventManager::class);
});
