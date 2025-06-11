<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Repositories;

use Illuminate\Container\Container;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Theme\Domain\Contracts\ThemeDiscoveryInterface;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

class ThemeRepository implements ModuleRepositoryInterface
{
    protected array $cachedThemes = [];

    protected bool $cacheLoaded = false;

    public function __construct(
        protected Container $app,
        protected ThemeDiscoveryInterface $discovery,
        protected WordPressThemeParser $themeParser,
        protected CollectionFactoryInterface $collectionFactory
    ) {}

    public function all(): array
    {
        if (! $this->cacheLoaded) {
            $this->loadThemes();
        }

        return $this->cachedThemes;
    }

    public function toCollection(): CollectionInterface
    {
        return $this->collectionFactory->make($this->all());
    }

    public function find(string $name): ?ModuleInterface
    {
        $themes = $this->all();
        $lowerName = strtolower($name);

        return $themes[$lowerName] ?? null;
    }

    public function findOrFail(string $name): ModuleInterface
    {
        $module = $this->find($name);

        if ($module === null) {
            throw ModuleException::notFound($name);
        }

        return $module;
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    public function getByStatus(bool $status): array
    {
        return array_filter($this->all(), function (ModuleInterface $module) use ($status) {
            return $module->isEnabled() === $status;
        });
    }

    public function allEnabled(): array
    {
        return $this->getByStatus(true);
    }

    public function allDisabled(): array
    {
        return $this->getByStatus(false);
    }

    public function getOrdered(string $direction = 'asc'): array
    {
        $themes = $this->allEnabled();

        uasort($themes, function (ModuleInterface $a, ModuleInterface $b) use ($direction) {
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

        return $themes;
    }

    public function scan(): array
    {
        $this->loadThemes(true);

        return $this->cachedThemes;
    }

    public function register(): void
    {
        foreach ($this->getOrdered() as $theme) {
            if ($theme instanceof ThemeModuleInterface && $theme->isEnabled()) {
                $theme->register();
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->getOrdered() as $theme) {
            if ($theme instanceof ThemeModuleInterface && $theme->isEnabled()) {
                $theme->boot();
            }
        }
    }

    public function count(): int
    {
        return count($this->all());
    }

    protected function loadThemes(bool $forceReload = false): void
    {
        if ($this->cacheLoaded && ! $forceReload) {
            return;
        }

        $this->cachedThemes = [];

        if ($forceReload) {
            $this->discovery->resetCache();
        }

        $discoveredThemes = $this->discovery->discoverThemes();

        /** @var ThemeModuleInterface $theme */
        foreach ($discoveredThemes as $theme) {
            $this->cachedThemes[strtolower($theme->getName())] = $theme;
        }

        $this->cacheLoaded = true;
    }

    public function resetCache(): static
    {
        $this->cacheLoaded = false;
        $this->cachedThemes = [];
        $this->discovery->resetCache();

        return $this;
    }
}
