<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Role\Domain\Contracts\RoleStoreInterface;
use Pollora\Role\Domain\Services\RoleCompiler;

/**
 * Writes the roles, as the code makes them, into the `{prefix}user_roles`
 * option: for a tool that reads the database without loading the site (an
 * export script, a badly written plugin), which otherwise never sees the
 * declared roles. The code stays the source of truth: the roles are injected
 * again on every request, and what Pollora wrote is marked, so a role later
 * removed from the code is still removed. Shows the changes unless --force is given.
 */
#[Description('Write the roles as the code makes them into the database, for tools that do not load the site')]
#[Signature('pollora:roles:dump {--force : Write the roles; without it, only show the changes}')]
class RoleDumpCommand extends Command
{
    public function handle(RoleStoreInterface $store): int
    {
        if (! function_exists('wp_roles')) {
            $this->components->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $compiled = \wp_roles()->roles;
        $stored = $store->storedRoles();
        $changes = [];

        foreach ($compiled as $slug => $role) {
            $before = $stored[$slug] ?? null;

            $changes[$slug] = match (true) {
                $before === null => 'added',
                $this->withoutMarker($before) != $this->withoutMarker($role) => 'changed',
                default => null,
            };
        }

        foreach (array_diff_key($stored, $compiled) as $slug => $role) {
            $changes[$slug] = 'removed';
        }

        $changes = array_filter($changes);

        // Pollora's markers alone do not matter to a tool reading the roles
        if ($changes === []) {
            $this->components->info('The database already holds the roles as the code makes them.');

            return self::SUCCESS;
        }

        foreach ($changes as $slug => $change) {
            $this->components->twoColumnDetail($slug, $change);
        }

        if (! $this->option('force')) {
            $this->components->warn('Dry run: nothing changed. Run again with --force to write the roles.');

            return self::SUCCESS;
        }

        $store->saveStoredRoles($compiled);
        $this->components->info(sprintf('%d role(s) written to the database.', count($compiled)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    private function withoutMarker(array $role): array
    {
        unset($role[RoleCompiler::MARKER]);

        if (is_array($role['capabilities'] ?? null)) {
            ksort($role['capabilities']);
        }

        return $role;
    }
}
