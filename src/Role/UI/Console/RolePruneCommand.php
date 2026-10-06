<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Contracts\RoleStoreInterface;
use Pollora\Role\Domain\Contracts\RoleUsageInterface;
use Pollora\Role\Domain\Models\RoleDefinition;
use Pollora\Role\Domain\Services\RoleCompiler;
use Pollora\Role\Domain\Services\StoredEntryClassifier;

/**
 * Cleans up after roles removed from the code: takes the dead role off the
 * users who still carry it (giving those left with no role the --reassign
 * one), and deletes the copies of removed roles a plugin wrote back to the
 * database. Shows what it would do unless --force is given.
 */
#[Description('Take roles removed from the code off users and out of the database')]
#[Signature('pollora:roles:prune {--reassign= : Role given to users left with no role} {--force : Apply the changes; without it, only show them}')]
class RolePruneCommand extends Command
{
    public function handle(RoleUsageInterface $usage, RoleStoreInterface $store, RoleRegistry $registry, StoredEntryClassifier $classifier): int
    {
        if (! function_exists('wp_roles')) {
            $this->components->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $roles = \wp_roles()->roles;
        $reassign = $this->option('reassign');
        $reassign = is_string($reassign) && $reassign !== '' ? $reassign : null;

        if ($reassign !== null && ! isset($roles[$reassign])) {
            $this->components->error(sprintf('WordPress has no role "%s" to reassign.', $reassign));

            return self::FAILURE;
        }

        // Users: the dead entries each one carries, and whether a role is left
        $plan = [];

        foreach (array_keys($usage->entries()) as $entry) {
            if ($classifier->classify($entry, $roles) !== StoredEntryClassifier::DEAD) {
                continue;
            }

            foreach ($usage->usersWith($entry) as $userId => $login) {
                $plan[$userId]['login'] = $login;
                $plan[$userId]['remove'][] = $entry;
            }
        }

        $orphans = [];

        foreach ($plan as $userId => $user) {
            $plan[$userId]['roleless'] = $store->rolesOf($userId) === [];

            if ($plan[$userId]['roleless']) {
                $orphans[] = $user['login'];
            }
        }

        // Stored copies of roles the code declared and no longer does
        $declared = array_map(static fn (RoleDefinition $definition): string => $definition->slug, $registry->definitions());
        $stored = $store->storedRoles();
        $copies = array_keys(array_filter(
            $stored,
            static fn (array $role, string $slug): bool => ($role[RoleCompiler::MARKER]['managed'] ?? false) === true && ! in_array($slug, $declared, true),
            ARRAY_FILTER_USE_BOTH
        ));

        if ($plan === [] && $copies === []) {
            $this->components->info('Nothing to prune: every role users carry exists.');

            return self::SUCCESS;
        }

        foreach ($plan as $user) {
            $this->components->twoColumnDetail(
                $user['login'],
                sprintf('remove %s', implode(', ', $user['remove'])).($user['roleless'] ? ($reassign === null ? ' <fg=yellow>→ no role left</>' : ' → '.$reassign) : '')
            );
        }

        foreach ($copies as $slug) {
            $this->components->twoColumnDetail(sprintf('stored role "%s"', $slug), 'delete the copy a plugin wrote back');
        }

        if ($orphans !== [] && $reassign === null) {
            $this->components->error(sprintf('%d user(s) would be left with no role: pass --reassign=subscriber (or another role).', count($orphans)));

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->warn('Dry run: nothing changed. Run again with --force to apply.');

            return self::SUCCESS;
        }

        foreach ($plan as $userId => $user) {
            foreach ($user['remove'] as $entry) {
                $store->removeFromUser($userId, $entry);
            }

            if ($user['roleless'] && $reassign !== null) {
                $store->addRoleToUser($userId, $reassign);
            }
        }

        if ($copies !== []) {
            $store->saveStoredRoles(array_diff_key($stored, array_flip($copies)));
        }

        $this->components->info(sprintf('Pruned: %d user(s), %d stored role(s).', count($plan), count($copies)));

        return self::SUCCESS;
    }
}
