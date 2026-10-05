<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Super roles
    |--------------------------------------------------------------------------
    |
    | Roles that receive every capability the project declares: the
    | capabilities of post types with #[CapabilityType], of taxonomies with
    | their own capabilities, and the cases of #[CapabilitySet] enums. Without
    | it, a post type with its own capabilities disappears from the admin,
    | administrators included.
    |
    | A #[Role] cannot inherit from a super role.
    |
    */
    'super_roles' => ['administrator'],
];
