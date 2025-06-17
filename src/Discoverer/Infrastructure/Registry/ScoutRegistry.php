<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Infrastructure\Registry;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pollora\Discoverer\Domain\Contracts\HandlerScoutInterface;
use Pollora\Discoverer\Domain\Contracts\ScoutRegistryInterface;
use RuntimeException;
use Spatie\StructureDiscoverer\StructureScout;

/**
 * Simple in-memory registry for managing scout registration and discovery.
 *
 * This implementation provides a lightweight registry that stores scout classes
 * by key and executes discovery operations on demand. It validates scout classes
 * to ensure they extend StructureScout for proper integration with the Spatie package.
 */
final class ScoutRegistry implements ScoutRegistryInterface
{
    /**
     * Map of scout keys to their corresponding class names.
     *
     * @var array<string, string>
     */
    private array $scouts = [];

    /**
     * @param  Container  $container  Laravel container for dependency injection
     * @param  array<string, string>  $scouts  Initial scouts to register
     */
    public function __construct(
        private readonly Container $container,
        array $scouts = []
    ) {
        foreach ($scouts as $key => $scoutClass) {
            $this->register($key, $scoutClass);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function register(string $key, string $scoutClass): void
    {
        $this->validateScoutClass($scoutClass);
        $this->scouts[$key] = $scoutClass;
    }

    /**
     * {@inheritDoc}
     */
    public function discover(string $key): Collection
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Scout '{$key}' not found. Available scouts: ".implode(', ', $this->getRegistered()));
        }

        try {
            $scoutClass = $this->scouts[$key];
            $scout = $this->container->make($scoutClass);

            if (! $scout instanceof StructureScout) {
                throw new RuntimeException("Scout '{$scoutClass}' must extend StructureScout");
            }

            $discovered = $scout->get();

            return collect($discovered);
        } catch (\Throwable $e) {
            throw new RuntimeException("Discovery failed for scout '{$key}': ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Exécute la découverte et traite automatiquement les classes découvertes si le scout implémente HandlerScoutInterface.
     *
     * @param  string  $key  La clé du scout à utiliser pour la découverte
     * @return Collection<int, string> Collection des noms de classes découvertes
     *
     * @throws InvalidArgumentException Quand la clé du scout n'est pas trouvée
     * @throws RuntimeException Quand la découverte ou le traitement échoue
     */
    public function discoverAndHandle(string $key): Collection
    {
        $discoveredClasses = $this->discover($key);

        if ($discoveredClasses->isEmpty()) {
            return $discoveredClasses;
        }

        try {
            $scoutClass = $this->scouts[$key];
            $scout = $this->container->make($scoutClass);

            // Si le scout implémente HandlerScoutInterface, on appelle sa méthode handle()
            if ($scout instanceof HandlerScoutInterface) {
                $scout->handle($discoveredClasses);
            }

            return $discoveredClasses;
        } catch (\Throwable $e) {
            throw new RuntimeException("Handler execution failed for scout '{$key}': ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRegistered(): array
    {
        return array_keys($this->scouts);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return isset($this->scouts[$key]);
    }

    /**
     * Validate that a scout class exists and extends StructureScout.
     *
     * @param  string  $scoutClass  The scout class to validate
     *
     * @throws InvalidArgumentException When the scout class is invalid
     */
    private function validateScoutClass(string $scoutClass): void
    {
        if (! class_exists($scoutClass)) {
            throw new InvalidArgumentException("Scout class '{$scoutClass}' does not exist");
        }

        if (! is_subclass_of($scoutClass, StructureScout::class)) {
            throw new InvalidArgumentException("Scout class '{$scoutClass}' must extend ".StructureScout::class);
        }
    }
}
