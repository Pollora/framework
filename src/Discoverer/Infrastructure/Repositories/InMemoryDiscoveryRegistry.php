<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Repositories;

use Pollora\Discoverer\Domain\Contracts\DiscoveryRegistryInterface;
use Pollora\Discoverer\Domain\Models\DiscoveredClass;

/**
 * In-memory implementation of the discovery registry.
 */
final class InMemoryDiscoveryRegistry implements DiscoveryRegistryInterface
{
    /**
     * @var array<string, array<DiscoveredClass>>
     */
    private array $registry = [];

    /**
     * @var array<string, bool>
     */
    private array $classMap = [];

    public function register(DiscoveredClass $discoveredClass): void
    {
        $this->registry[$discoveredClass->getType()][] = $discoveredClass;
        $this->classMap[$discoveredClass->getClassName()] = true;
    }

    public function getByType(string $type): array
    {
        return $this->registry[$type] ?? [];
    }

    public function has(string $className): bool
    {
        return isset($this->classMap[$className]);
    }

    public function all(): array
    {
        return $this->registry;
    }
}
