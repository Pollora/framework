<?php

declare(strict_types=1);

namespace Pollora\Discovery\Infrastructure\Services;

use Illuminate\Container\Container;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Psr\Log\LoggerInterface;

/**
 * Automatically registers Discovery classes from the service container.
 *
 * Scans the container bindings for classes that implement DiscoveryInterface
 * and registers them with the engine — eliminating the need for manual
 * $engine->addDiscovery() calls in each ServiceProvider.
 *
 * Each ServiceProvider still registers its Discovery as a singleton in
 * register(). The registrar picks them up and adds them to the engine.
 */
final readonly class DiscoveryRegistrar
{
    public function __construct(
        private Container $container,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Scan container bindings and register any DiscoveryInterface implementations.
     *
     * @param  DiscoveryEngineInterface  $engine  The engine to register discoveries with
     * @return array<string> Identifiers of auto-registered discoveries
     */
    public function registerFromContainer(DiscoveryEngineInterface $engine): array
    {
        $registered = [];

        foreach ($this->container->getBindings() as $abstract => $binding) {
            if (! is_string($abstract) || ! $this->looksLikeDiscovery($abstract)) {
                continue;
            }

            try {
                $instance = $this->container->make($abstract);

                if (! $instance instanceof DiscoveryInterface) {
                    continue;
                }

                $identifier = $instance->getIdentifier();

                if ($engine->getDiscoveries()->has($identifier)) {
                    continue;
                }

                $engine->addDiscovery($identifier, $instance);
                $registered[] = $identifier;
            } catch (\Throwable $e) {
                $this->logger?->debug(
                    sprintf('DiscoveryRegistrar: skipping %s — %s', $abstract, $e->getMessage())
                );
            }
        }

        return $registered;
    }

    /**
     * Quick check if a binding name could be a Discovery class.
     *
     * Avoids resolving every binding in the container — only tries
     * classes whose name ends with "Discovery".
     */
    private function looksLikeDiscovery(string $abstract): bool
    {
        return str_ends_with($abstract, 'Discovery');
    }
}
