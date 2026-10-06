<?php

declare(strict_types=1);

namespace Pollora\Role\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Role\Application\Services\RoleRegistry;
use Pollora\Role\Domain\Contracts\RoleUsageInterface;

/**
 * Lists the roles WordPress has, as the code makes them: where each comes
 * from (declared by a class, modified by one, or stored), how many
 * capabilities it gives and how many users carry it.
 */
#[Description('List the WordPress roles, their origin, capabilities and users')]
#[Signature('pollora:roles:list {--json : Output as JSON}')]
class RoleListCommand extends Command
{
    public function handle(RoleRegistry $registry, RoleUsageInterface $usage): int
    {
        if (! function_exists('wp_roles')) {
            $this->components->error('WordPress is not loaded.');

            return self::FAILURE;
        }

        $declared = [];
        $modified = [];

        foreach ($registry->definitions() as $definition) {
            $declared[$definition->slug] = $definition->declaringClass;
        }

        foreach ($registry->modifications() as $modification) {
            $modified[$modification->slug][] = $modification->declaringClass;
        }

        $users = $usage->entries();
        $list = [];

        foreach (\wp_roles()->roles as $slug => $role) {
            $list[] = [
                'slug' => $slug,
                'label' => $role['name'],
                'origin' => match (true) {
                    isset($declared[$slug]) => 'declared by '.$declared[$slug],
                    isset($modified[$slug]) => 'stored, modified by '.implode(', ', $modified[$slug]),
                    default => 'stored',
                },
                'capabilities' => count(array_filter($role['capabilities'] ?? [])),
                'users' => $users[$slug] ?? 0,
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(
            ['Role', 'Label', 'Origin', 'Capabilities', 'Users'],
            array_map(static fn (array $role): array => [$role['slug'], $role['label'], $role['origin'], $role['capabilities'], $role['users']], $list),
        );

        return self::SUCCESS;
    }
}
