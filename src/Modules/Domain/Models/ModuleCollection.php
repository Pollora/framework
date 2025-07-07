<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Models;

use Illuminate\Support\Collection;
use Pollora\Modules\Domain\Contracts\ModuleInterface;

class ModuleCollection extends Collection
{
    /**
     * Get modules by status.
     */
    public function getByStatus(bool $status): static
    {
        return $this->filter(fn (ModuleInterface $module): bool => $module->isEnabled() === $status);
    }

    /**
     * Get enabled modules.
     */
    public function enabled(): static
    {
        return $this->getByStatus(true);
    }

    /**
     * Get disabled modules.
     */
    public function disabled(): static
    {
        return $this->getByStatus(false);
    }

    /**
     * Get ordered modules by priority.
     */
    public function ordered(string $direction = 'asc'): static
    {
        return $this->sort(function (ModuleInterface $a, ModuleInterface $b) use ($direction): int {
            $priorityA = (int) $a->get('priority', 0);
            $priorityB = (int) $b->get('priority', 0);

            if ($priorityA === $priorityB) {
                return 0;
            }

            if ($direction === 'desc') {
                return $priorityA < $priorityB ? 1 : -1;
            }

            return $priorityA > $priorityB ? 1 : -1;
        });
    }

    /**
     * Find module by name.
     */
    public function findByName(string $name): ?ModuleInterface
    {
        return $this->first(fn (ModuleInterface $module): bool => strtolower($module->getName()) === strtolower($name));
    }

    /**
     * Check if module exists by name.
     */
    public function hasByName(string $name): bool
    {
        return $this->findByName($name) instanceof \Pollora\Modules\Domain\Contracts\ModuleInterface;
    }

    /**
     * Get the collection as an array suitable for WordPress themes.
     */
    public function toThemeArray(): array
    {
        return $this->mapWithKeys(fn (ModuleInterface $module) => [
            $module->getLowerName() => [
                'name' => $module->getName(),
                'description' => $module->getDescription(),
                'path' => $module->getPath(),
                'enabled' => $module->isEnabled(),
            ],
        ])->toArray();
    }
}
