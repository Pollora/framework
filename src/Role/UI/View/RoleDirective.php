<?php

declare(strict_types=1);

namespace Pollora\Role\UI\View;

use Pollora\Role\Application\Services\RoleReference;

/**
 * `@role('editor', 'author')` or `@role(EventManager::class)`: shows its content
 * when the current user has one of the roles.
 *
 * Replaces the `@role` of Sage Directives, which compared slugs only, and keeps
 * its behaviour for them: case is ignored, a guest never matches.
 */
final class RoleDirective
{
    public function __invoke(string $expression): string
    {
        return sprintf(
            '<?php if (is_user_logged_in() && \\%s::matchesAny((array) wp_get_current_user()->roles, [%s])) : ?>',
            RoleReference::class,
            $expression
        );
    }
}
