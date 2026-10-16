<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use BackedEnum;
use Illuminate\Support\Facades\Facade;
use Pollora\Role\Application\Services\RoleDeclaration;
use Pollora\Role\Application\Services\RoleDeclarer;

/**
 * Facade for declaring roles and capabilities in code, the fluent way.
 *
 * The same roles as the `#[Role]` and `#[ModifyRole]` attributes, checked by
 * the same code. There is nothing to register: a declaration is checked and
 * handed to WordPress when it builds its roles.
 *
 * ```php
 * Role::make('event_manager', 'Event manager')
 *     ->inherits('author')
 *     ->grantsPostType(Event::class, Access::Editor)
 *     ->grants('export_attendees')
 *     ->without('publish_posts');
 * ```
 *
 * @method static RoleDeclaration make(string $slug, string $label) Declare a role
 * @method static RoleDeclaration modify(string $slug) Adjust a role WordPress already has
 * @method static void capabilitySet(class-string<BackedEnum>|list<string> $capabilities) Declare capabilities of the project
 *
 * @see RoleDeclarer
 */
class Role extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return RoleDeclarer::class;
    }
}
