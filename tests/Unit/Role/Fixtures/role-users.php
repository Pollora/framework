<?php

declare(strict_types=1);

// A user model and its WP_User, with roles held in memory.

use Pollora\Models\User;

if (! class_exists('WP_User')) {
    eval('class WP_User { public int $ID = 0; }');
}

/**
 * A WP_User holding roles in memory, recording what is added and removed.
 *
 * @param  list<string>  $roles
 */
function wpUserWithRoles(array $roles): WP_User
{
    return new class($roles) extends WP_User
    {
        /** @param  list<string>  $roles */
        public function __construct(public array $roles) {}

        public function add_role(string $role): void
        {
            $this->roles[] = $role;
        }

        public function remove_role(string $role): void
        {
            $this->roles = array_values(array_diff($this->roles, [$role]));
        }
    };
}

/**
 * A user model whose WP_User is the given one.
 */
function userWithWpUser(WP_User $wpUser): User
{
    $user = new class extends User
    {
        public WP_User $wpUser;

        public function toWpUser(): WP_User
        {
            return $this->wpUser;
        }
    };
    $user->wpUser = $wpUser;

    return $user;
}
