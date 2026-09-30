<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Checks;

use Pollora\Discovery\Application\Services\DiscoveryManager;
use Pollora\Discovery\Infrastructure\Services\DiscoveryCacheManager;
use Pollora\Doctor\Domain\Contracts\CheckInterface;
use Pollora\Doctor\Domain\Enums\RunContext;
use Pollora\Doctor\Domain\Models\CheckResult;

/**
 * The discovery cache knows every class that carries an attribute.
 *
 * Outside debug mode, discovery reads a cache: a class added since — a new
 * #[Action], #[PostType], #[WpRestRoute] — is not registered, and nothing says so
 * until someone runs discovery:clear. Compared against a fresh scan of each
 * location, so the console only: it reads every file discovery covers.
 */
final readonly class DiscoveryCacheCheck implements CheckInterface
{
    public function __construct(
        private DiscoveryManager $discovery,
        private DiscoveryCacheManager $cache,
    ) {}

    public function id(): string
    {
        return 'discovery-cache';
    }

    public function label(): string
    {
        return 'Discovery cache';
    }

    public function runsIn(): array
    {
        return [RunContext::Console];
    }

    public function run(RunContext $context): CheckResult
    {
        if (! $this->cache->isCacheEnabled()) {
            return CheckResult::ok('The cache is off (debug mode): discovery reads the disk on every request.');
        }

        $missing = [];

        foreach ($this->discovery->getLocations() as $location) {
            foreach ($this->cache->classesMissingFromCache($location) ?? [] as $class) {
                $missing[] = $class;
            }
        }

        if ($missing !== []) {
            sort($missing);

            return CheckResult::error(
                sprintf('%d class(es) added since the cache was written are not registered.', count($missing)),
                array_slice($missing, 0, 20),
                'php artisan discovery:clear',
            );
        }

        return CheckResult::ok('The cache matches the classes on disk.');
    }
}
