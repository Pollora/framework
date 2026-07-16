<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Psr\Log\LoggerInterface;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;

/**
 * Automatically registers Discovery classes found in scanned locations.
 *
 * Scans Spatie's discovered structures for classes that implement
 * DiscoveryInterface and registers them with the engine — eliminating
 * the need for manual $engine->addDiscovery() calls in each ServiceProvider.
 *
 * Respects manual registrations: if a discovery identifier is already
 * registered, the auto-discovered one is skipped (manual takes precedence).
 */
final readonly class DiscoveryRegistrar
{
    public function __construct(
        private Container $container,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Scan discovered structures and register any DiscoveryInterface implementations.
     *
     * @param  array<DiscoveredStructure>  $structures  Spatie-discovered structures
     * @param  DiscoveryEngineInterface  $engine  The engine to register discoveries with
     * @return array<string> Identifiers of auto-registered discoveries
     */
    public function registerFromStructures(array $structures, DiscoveryEngineInterface $engine): array
    {
        $registered = [];

        foreach ($structures as $structure) {
            if (! $this->isDiscoveryCandidate($structure)) {
                continue;
            }

            $className = $structure->namespace.'\\'.$structure->name;

            try {
                $discovery = $this->resolveDiscovery($className);

                if (! $discovery instanceof DiscoveryInterface) {
                    continue;
                }

                $identifier = $discovery->getIdentifier();

                // Manual registration takes precedence
                if ($engine->getDiscoveries()->has($identifier)) {
                    continue;
                }

                $engine->addDiscovery($identifier, $discovery);
                $registered[] = $identifier;
            } catch (\Throwable $e) {
                $this->logger?->debug(
                    sprintf('DiscoveryRegistrar: skipping %s — %s', $className, $e->getMessage())
                );
            }
        }

        return $registered;
    }

    /**
     * Check if a structure is a potential Discovery class using Spatie's
     * token-parsed data (no reflection needed).
     */
    private function isDiscoveryCandidate(DiscoveredStructure $structure): bool
    {
        if (! $structure instanceof DiscoveredClass) {
            return false;
        }

        if ($structure->isAbstract) {
            return false;
        }

        // Check implements chain for DiscoveryInterface
        if ($structure->implementsChain !== null) {
            foreach ($structure->implementsChain as $interface) {
                if ($interface === DiscoveryInterface::class) {
                    return true;
                }
            }
        }

        // Check direct implements
        return in_array(DiscoveryInterface::class, $structure->implements, true);
    }

    /**
     * Resolve a Discovery class from the container.
     *
     * Returns null if the class cannot be resolved (missing dependencies,
     * not a valid discovery, etc.)
     */
    private function resolveDiscovery(string $className): ?DiscoveryInterface
    {
        try {
            if (! class_exists($className, true)) {
                return null;
            }

            $instance = $this->container->make($className);

            if (! $instance instanceof DiscoveryInterface) {
                return null;
            }

            return $instance;
        } catch (\Throwable) {
            return null;
        }
    }
}
