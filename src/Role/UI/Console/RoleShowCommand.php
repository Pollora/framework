<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Role\Application\Services\RoleReference;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Models\RoleDefinition;
use Pollora\Role\Domain\Services\RoleCompiler;

/**
 * Shows the effective capabilities of a role, each with where it comes from,
 * and what the code takes away: what a reviewer needs to see who can do what.
 */
#[Description('Show the effective capabilities of a role and where each comes from')]
#[Signature('pollora:roles:show {role : A role slug or a #[Role] class} {--json : Output as JSON}')]
class RoleShowCommand extends Command
{
    public function handle(RoleRegistry $registry): int
    {
        if (! function_exists('wp_roles')) {
            $this->components->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $slug = RoleReference::slug((string) $this->argument('role'));
        $roles = \wp_roles()->roles;
        $role = $roles[$slug] ?? null;

        if (! is_array($role)) {
            $this->components->error(sprintf('WordPress has no role "%s". php artisan pollora:roles:list lists them.', $slug));

            return self::FAILURE;
        }

        $definition = null;

        foreach ($registry->definitions() as $candidate) {
            if ($candidate->slug === $slug) {
                $definition = $candidate;
            }
        }

        $superRoles = (array) config('roles.super_roles', ['administrator']);
        $declaredCapabilities = in_array($slug, $superRoles, true) ? $registry->declaredCapabilities() : [];
        $marker = is_array($role[RoleCompiler::MARKER] ?? null) ? $role[RoleCompiler::MARKER] : [];
        $capabilities = [];

        foreach (array_keys(array_filter($role['capabilities'] ?? [])) as $capability) {
            $capabilities[] = ['capability' => $capability, 'from' => $this->origin($capability, $definition, $roles, $marker, $declaredCapabilities)];
        }

        usort($capabilities, static fn (array $a, array $b): int => strcmp($a['capability'], $b['capability']));
        $removed = $definition instanceof RoleDefinition ? $definition->changes->removals : array_keys($marker['removed'] ?? []);

        $result = [
            'slug' => $slug,
            'label' => $role['name'],
            'declared_by' => $definition?->declaringClass,
            'inherits' => $definition?->inherits,
            'capabilities' => $capabilities,
            'removed' => array_values($removed),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail(sprintf('<fg=green;options=bold>%s</> %s', $slug, $role['name']), $result['declared_by'] ?? 'stored in the database');

        if ($definition?->inherits !== null) {
            $this->components->twoColumnDetail('inherits', $definition->inherits);
        }

        $this->newLine();
        $this->table(['Capability', 'From'], array_map(static fn (array $row): array => [$row['capability'], $row['from']], $capabilities));

        if ($removed !== []) {
            $this->components->info('Removed by the code: '.implode(', ', $removed));
        }

        return self::SUCCESS;
    }

    /**
     * Where a capability of the role comes from.
     *
     * @param  array<string, array<string, mixed>>  $roles
     * @param  array<string, mixed>  $marker
     * @param  list<string>  $declaredCapabilities
     */
    private function origin(string $capability, ?RoleDefinition $definition, array $roles, array $marker, array $declaredCapabilities): string
    {
        if ($definition instanceof RoleDefinition) {
            return match (true) {
                in_array($capability, $definition->changes->grants, true) => 'granted by '.class_basename($definition->declaringClass),
                $definition->inherits !== null && ! empty($roles[$definition->inherits]['capabilities'][$capability]) => 'inherited from '.$definition->inherits,
                default => 'added by the code (#[ModifyRole], super role)',
            };
        }

        return match (true) {
            in_array($capability, $marker['granted'] ?? [], true) => in_array($capability, $declaredCapabilities, true) ? 'super role: declared by the project' : 'granted by #[ModifyRole]',
            default => 'stored',
        };
    }
}
