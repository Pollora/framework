<?php

declare(strict_types=1);

namespace Pollora\Modules\UI\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Pollora\Modules\Application\Services\ModuleVersions;

/**
 * Check now whether the modules installed by Composer have a newer release,
 * and refresh what the admin shows.
 */
#[Description('List the modules installed by Composer that have a newer release')]
#[Signature('pollora:module:outdated {--json : Output as JSON}')]
class ModuleOutdatedCommand extends Command
{
    public function handle(ModuleVersions $versions): int
    {
        $modules = $versions->refresh();

        if ($this->option('json')) {
            $this->line((string) json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($modules === []) {
            $this->components->info('No module is installed by Composer: local modules have no version to check.');

            return self::SUCCESS;
        }

        $this->table(['Module', 'Package', 'Installed', 'Latest', ''], array_map(
            fn (string $name, array $version): array => [
                $name,
                $version['package'],
                $version['version'],
                $version['latest'] ?? 'unknown',
                match (true) {
                    $version['development'] => 'development build',
                    $version['update'] => 'composer update '.$version['package'],
                    $version['latest'] === null => 'source did not answer',
                    default => 'up to date',
                },
            ],
            array_keys($modules),
            $modules,
        ));

        return self::SUCCESS;
    }
}
