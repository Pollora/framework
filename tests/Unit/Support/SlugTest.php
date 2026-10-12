<?php

declare(strict_types=1);

use Pollora\Support\Slug;

/**
 * Slug gives WP-CLI commands the names pollora/attributes derives for
 * #[WpCli] and #[Command]: both must agree, or a command answers to a name
 * its attribute never declared.
 */
it('derives a command from a class name, the Command suffix dropped', function (): void {
    expect(Slug::fromClassName('App\\Cli\\UserManagementCommand'))->toBe('user-management')
        ->and(Slug::fromClassName('Cache'))->toBe('cache');
});

it('derives a subcommand from a method name', function (): void {
    expect(Slug::fromMethodName('deleteById'))->toBe('delete-by-id')
        ->and(Slug::toKebabCase('listItems'))->toBe('list-items');
});
