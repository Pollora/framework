<?php

declare(strict_types=1);

namespace Pollora\Models\Concerns;

use InvalidArgumentException;
use Pollora\Role\Application\Services\RoleReference;

/**
 * WordPress roles on a user model. A role is named by its slug (`'editor'`) or
 * by the class of a `#[Role]` (`EventManager::class`).
 *
 * Writes go through `WP_User`, so WordPress's hooks (`add_user_role`,
 * `remove_user_role`) and user cache follow. They do not check the rights of
 * the code calling them, like `WP_User::set_role()`: check `promote_users`
 * where a user can trigger them.
 *
 * Requires a `toWpUser(): WP_User` method on the model.
 */
trait HasRoles
{
    /**
     * The slugs of the user's roles.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values($this->toWpUser()->roles);
    }

    /**
     * `$user->roles`, which Eloquent would otherwise read as a relation.
     *
     * @return list<string>
     */
    protected function getRolesAttribute(): array
    {
        return $this->roles();
    }

    /**
     * Whether the user has at least one of the roles.
     */
    public function hasRole(string ...$roles): bool
    {
        return RoleReference::matchesAny($this->roles(), $roles);
    }

    /**
     * Adds a role, keeping the ones the user already has.
     *
     * @throws InvalidArgumentException When WordPress does not know the role
     */
    public function assignRole(string $role): static
    {
        $slug = RoleReference::slug($role);

        if (! wp_roles()->is_role($slug)) {
            throw new InvalidArgumentException(sprintf('The role "%s" does not exist.', $slug));
        }

        $this->toWpUser()->add_role($slug);

        return $this;
    }

    /**
     * Removes a role, including one that no longer exists.
     */
    public function removeRole(string $role): static
    {
        $this->toWpUser()->remove_role(RoleReference::slug($role));

        return $this;
    }
}
