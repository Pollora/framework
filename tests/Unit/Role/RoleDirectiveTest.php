<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Role\UI\View\RoleDirective;
use Tests\Unit\Role\Fixtures\EventManager;

/**
 * Renders `@role(…) shown @endrole` for a visitor with the given roles, or a guest.
 *
 * @param  list<string>|null  $roles  null for a guest
 */
function renderRole(string $expression, ?array $roles): string
{
    Functions\when('is_user_logged_in')->justReturn($roles !== null);
    Functions\when('wp_get_current_user')->justReturn((object) ['roles' => $roles ?? []]);

    ob_start();
    eval('?>'.(new RoleDirective)($expression).'shown<?php endif; ?>');

    return (string) ob_get_clean();
}

it('shows its content for one of the slugs, ignoring case, as Sage did', function (): void {
    expect(renderRole("'editor', 'author'", ['author']))->toBe('shown')
        ->and(renderRole("'Editor'", ['editor']))->toBe('shown')
        ->and(renderRole("'editor'", ['subscriber']))->toBe('');
});

it('accepts the class of a #[Role]', function (): void {
    expect(renderRole('\\'.EventManager::class."::class, 'editor'", ['event_manager']))->toBe('shown');
});

it('hides its content from a guest', function (): void {
    expect(renderRole("'editor'", null))->toBe('');
});
