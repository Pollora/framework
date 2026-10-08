<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Checks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Env;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;
use Pollora\Modules\Infrastructure\Activation\ConnectorActivator;
use Pollora\Modules\Infrastructure\Activation\DatabaseStateConnector;
use Pollora\Modules\Infrastructure\Activation\JsonStateConnector;
use Throwable;

/**
 * Where module states come from, and whether a switch takes effect.
 *
 * - The database connector reads its fallback: the options table could not be read.
 * - The connector names a module that is no longer on disk.
 * - The state file cannot be written, while Plugins › Modules is the way to switch.
 * - The configuration or route cache was written before the last switch: the
 *   module's config and routes stay as they were.
 * - MODULES_* settings without a published config/modules.php: nwidart reads its
 *   activator while it registers, so they are never applied.
 */
final readonly class ModuleActivationCheck implements CheckInterface
{
    /**
     * Settings only config/modules.php applies.
     *
     * @var list<string>
     */
    private const array PUBLISHED_ONLY_SETTINGS = ['MODULES_CONNECTOR', 'MODULES_LOCKED_ENABLED', 'MODULES_LOCKED_DISABLED', 'MODULES_ENABLED', 'MODULES_DISABLED'];

    public function __construct(private Application $app) {}

    public function id(): string
    {
        return 'module-activation';
    }

    public function label(): string
    {
        return 'Module activation';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! $this->app->bound('modules') || ! $this->app->bound(ActivatorInterface::class)) {
            return CheckResult::skipped('nwidart/laravel-modules is not installed.');
        }

        $activator = $this->app->make(ActivatorInterface::class);
        $usesConnectorActivator = $activator instanceof ConnectorActivator;
        $connector = $activator instanceof ConnectorActivator
            ? $activator->connector()
            : new JsonStateConnector((string) $this->app->make('config')->get('modules.activators.file.statuses-file', $this->app->basePath('modules_statuses.json')));

        $warnings = [];
        $fixes = [];

        if ($connector instanceof DatabaseStateConnector && $connector->usesFallback()) {
            $warnings[] = sprintf('The %s option could not be read: module states come from the fallback (%s)', $connector->option(), $connector->fallback()->label());
            $fixes[] = 'check the database connection, or the options table of a fresh install';
        }

        $missing = array_diff(array_keys($connector->all()), $this->moduleNames());

        if ($missing !== []) {
            sort($missing);
            $warnings[] = sprintf('%s (%s) lists module(s) no longer on disk: %s', $connector->label(), $this->where($connector), implode(', ', $missing));
            $fixes[] = sprintf('remove them from %s', $this->where($connector));
        }

        if (! $connector->writable() && filter_var($this->app->make('config')->get('modules.admin.toggle', true), FILTER_VALIDATE_BOOLEAN)) {
            $warnings[] = sprintf('%s (%s) cannot be written: Plugins › Modules cannot switch modules', $connector->label(), $this->where($connector));
            $fixes[] = 'commit the module states, switch to the database connector (php artisan pollora:module:connector database --import), or set MODULES_ADMIN_TOGGLE=false';
        }

        $staleCache = $this->staleCache($connector);

        if ($staleCache !== null) {
            $warnings[] = sprintf("The %s cache was written before the last module switch: it still holds the previous modules' configuration and routes", $staleCache);
            $fixes[] = 'php artisan optimize:clear, then cache again';
        }

        if (! $usesConnectorActivator) {
            $ignored = array_values(array_filter(self::PUBLISHED_ONLY_SETTINGS, fn (string $name): bool => ! in_array(Env::get($name), [null, ''], true)));

            if ($ignored !== []) {
                $warnings[] = sprintf('%s set, but config/modules.php is not published: nwidart reads its activator before any provider, so they are ignored', implode(', ', $ignored));
                $fixes[] = 'php artisan vendor:publish --tag=pollora-modules';
            }
        }

        if ($warnings !== []) {
            return CheckResult::warning(sprintf('%d problem(s) with module states.', count($warnings)), $warnings, implode(' ; ', $fixes));
        }

        return CheckResult::ok(sprintf('Module states come from %s and apply on the next request.', $this->where($connector)));
    }

    /**
     * @return list<string>
     */
    private function moduleNames(): array
    {
        try {
            return array_values(array_map(fn (object $module): string => (string) $module->getName(), $this->app->make('modules')->all()));
        } catch (Throwable) {
            return [];
        }
    }

    private function where(object $connector): string
    {
        return match (true) {
            $connector instanceof JsonStateConnector => basename($connector->path()),
            $connector instanceof DatabaseStateConnector => 'the '.$connector->option().' option',
            default => $connector->label(),
        };
    }

    /**
     * The cache (configuration, routes) older than the state file, when the
     * connector is a file whose modification time says when it last changed.
     */
    private function staleCache(object $connector): ?string
    {
        if (! $connector instanceof JsonStateConnector || ! is_file($connector->path())) {
            return null;
        }

        $switchedAt = (int) filemtime($connector->path());

        foreach (['configuration' => $this->app->getCachedConfigPath(), 'route' => $this->app->getCachedRoutesPath()] as $cache => $path) {
            if (is_file($path) && (int) filemtime($path) < $switchedAt) {
                return $cache;
            }
        }

        return null;
    }
}
