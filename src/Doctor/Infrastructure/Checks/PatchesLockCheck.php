<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Composer\InstalledVersions;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * patches.lock.json lists the patches the installed framework declares.
 *
 * composer-patches 2 applies what the lock says, not what the dependencies
 * declare: with a lock missing or older than the framework, `composer install`
 * skips the new patch and exits 0.
 */
final readonly class PatchesLockCheck implements CheckInterface
{
    public function id(): string
    {
        return 'patches-lock';
    }

    public function label(): string
    {
        return 'Composer patches lock';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        $declared = $this->declaredPatches();

        if ($declared === []) {
            return CheckResult::ok('The installed framework declares no patch.');
        }

        $lockFile = base_path('patches.lock.json');

        if (! is_file($lockFile)) {
            return CheckResult::error(
                "patches.lock.json is missing: Composer does not apply the framework's patches.",
                [$lockFile],
                'composer patches-relock && composer patches-repatch',
            );
        }

        $locked = json_decode((string) file_get_contents($lockFile), true)['patches'] ?? [];
        $missing = [];

        foreach ($declared as $package => $patches) {
            $lockedUrls = array_column(is_array($locked[$package] ?? null) ? $locked[$package] : [], 'url');

            foreach ($patches as $description => $url) {
                if (! in_array($url, $lockedUrls, true)) {
                    $missing[] = sprintf('%s: %s', $package, $description);
                }
            }
        }

        if ($missing !== []) {
            return CheckResult::error(
                "patches.lock.json is older than the framework's patches: Composer applies the old ones, or none.",
                $missing,
                'composer patches-relock && composer patches-repatch',
            );
        }

        return CheckResult::ok('patches.lock.json lists every patch the framework declares.');
    }

    /**
     * @return array<string, array<string, string>> package => [description => url]
     */
    private function declaredPatches(): array
    {
        $path = InstalledVersions::isInstalled('pollora/framework') ? InstalledVersions::getInstallPath('pollora/framework') : null;

        if ($path === null || ! is_file($path.'/composer.json')) {
            return [];
        }

        $patches = json_decode((string) file_get_contents($path.'/composer.json'), true)['extra']['patches'] ?? [];

        return is_array($patches) ? $patches : [];
    }
}
