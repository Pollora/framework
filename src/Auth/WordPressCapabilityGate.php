<?php

declare(strict_types=1);

namespace Pollora\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use WP_User;

/**
 * Answers Laravel's Gate with WordPress capabilities.
 *
 * Registered as a `Gate::after()` callback: abilities defined with
 * `Gate::define()` and policies keep priority, and any ability they leave
 * undecided is checked with `user_can()`. That makes `$user->can('edit_posts')`,
 * `Gate::allows('edit_post', $post)`, `@can` and the `can:` middleware follow
 * WordPress roles and capabilities.
 *
 * The Gate's user (a `Pollora\Models\User`, a `WP_User` or an ID) and Eloquent
 * model arguments are converted to what WordPress expects. WordPress may not be
 * loaded yet when the callback is registered, so its presence is checked when
 * the Gate asks.
 */
final class WordPressCapabilityGate
{
    /**
     * @param  mixed  $user  The user the Gate checks, null for a guest
     * @param  string  $ability  The ability or WordPress capability
     * @param  mixed  $result  The result decided so far (null when undecided)
     * @param  array<mixed>  $arguments  The ability arguments, such as a post
     */
    public function __invoke(mixed $user, string $ability, mixed $result, array $arguments): ?bool
    {
        if (! function_exists('user_can')) {
            return null;
        }

        $wordPressUser = $this->resolveUser($user);

        if ($wordPressUser === null) {
            return null;
        }

        return user_can($wordPressUser, $ability, ...array_map($this->resolveArgument(...), array_values($arguments)));
    }

    private function resolveUser(mixed $user): WP_User|int|null
    {
        return match (true) {
            $user instanceof WP_User, is_int($user) => $user,
            $user instanceof Authenticatable => (int) $user->getAuthIdentifier(),
            default => null,
        };
    }

    private function resolveArgument(mixed $argument): mixed
    {
        return $argument instanceof Model ? $argument->getKey() : $argument;
    }
}
