<?php

declare(strict_types=1);

namespace Pollora\Role\Infrastructure\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `role:` middleware: refuses with a 403 a user who has none of the roles.
 *
 *     Route::middleware('role:event_manager,editor')->group(…);
 *     Route::middleware(EnsureUserHasRole::using(EventManager::class))->group(…);
 *
 * Prefer `can:` with a capability: it stays right when a second role is given
 * that capability.
 */
class EnsureUserHasRole
{
    /**
     * The middleware string for the given roles, slugs or `#[Role]` classes.
     */
    public static function using(string ...$roles): string
    {
        return static::class.':'.implode(',', $roles);
    }

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException When the user is a guest or has none of the roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! is_object($user) || ! method_exists($user, 'hasRole') || ! $user->hasRole(...$roles)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
