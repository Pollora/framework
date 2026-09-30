<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Illuminate\Contracts\Foundation\Application;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * No Laravel cache freezes the configuration or the routes outside production.
 *
 * With the configuration cached, .env is no longer read: a changed APP_URL or
 * database setting is ignored without a word, and so is a new route while the
 * routes are cached. In production that is the point; while developing it is a
 * trap.
 */
final readonly class DevelopmentCachesCheck implements CheckInterface
{
    public function __construct(private Application $app) {}

    public function id(): string
    {
        return 'development-caches';
    }

    public function label(): string
    {
        return 'Configuration and route caches';
    }

    public function runsIn(): array
    {
        return [RunContext::Console, RunContext::Http];
    }

    public function run(RunContext $context): CheckResult
    {
        if ($this->app->environment('production')) {
            return CheckResult::ok('Production: caches are expected.');
        }

        $cached = [];

        if ($this->app->configurationIsCached()) {
            $cached[] = 'configuration cached ('.$this->app->getCachedConfigPath().'): changes to .env and config/ are ignored';
        }

        if ($this->app->routesAreCached()) {
            $cached[] = 'routes cached ('.$this->app->getCachedRoutesPath().'): changes to routes/ are ignored';
        }

        if ($cached !== []) {
            return CheckResult::warning(
                sprintf('Caches meant for production are on in the %s environment.', $this->app->environment()),
                $cached,
                'php artisan optimize:clear',
            );
        }

        return CheckResult::ok('Nothing freezes the configuration or the routes.');
    }
}
