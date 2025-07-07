<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Repositories;

use Illuminate\Container\Container;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Modules\Domain\Contracts\ModuleInterface;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;

/**
 * Theme repository implementation for the modular theme system.
 *
 * This repository integrates with the new self-registration system where themes
 * register themselves via their functions.php file through the ThemeRegistrarInterface.
 * It automatically synchronizes with the registrar to provide access to active themes
 * through the standard ModuleRepositoryInterface contract.
 */
class ThemeRepository implements ModuleRepositoryInterface
{
    protected array $cachedThemes = [];

    protected bool $cacheLoaded = false;

    public function __construct(
        protected Container $app,
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

        if (! $module instanceof \Pollora\Modules\Domain\Contracts\ModuleInterface) {
            throw ModuleException::notFound($name);
        }

        return $module;
    }

    public function has(string $name): bool
    {
        return $this->find($name) instanceof \Pollora\Modules\Domain\Contracts\ModuleInterface;
    }

    public function getByStatus(bool $status): array
    {
        return array_filter($this->all(), fn (ModuleInterface $module): bool => $module->isEnabled() === $status);
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

        uasort($themes, function (ModuleInterface $a, ModuleInterface $b) use ($direction): int {
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

    /**
     * Force synchronization with the theme registrar.
     *
     * This method forces a reload of themes from the registrar service,
     * ensuring that any newly registered themes are immediately available
     * in the repository.
     */
    public function syncWithRegistrar(): static
    {
        $this->loadThemes(true);

        return $this;
    }

    /**
     * Load themes from the registrar service.
     *
     * With the new self-registration system, themes register themselves via their functions.php file
     * through the ThemeRegistrarInterface. This method retrieves the active theme from the registrar
     * and ensures it's available through the repository interface.
     *
     * @param  bool  $forceReload  Whether to force a reload even if cache is already loaded
     */
    protected function loadThemes(bool $forceReload = false): void
    {
        if ($this->cacheLoaded && ! $forceReload) {
            return;
        }

        $this->cachedThemes = [];

        // Get the active theme from the registrar if available
        if ($this->app->bound(ThemeRegistrarInterface::class)) {
            try {
                /** @var ThemeRegistrarInterface $registrar */
                $registrar = $this->app->make(ThemeRegistrarInterface::class);
                $activeTheme = $registrar->getActiveTheme();

                if ($activeTheme instanceof ThemeModuleInterface) {
                    $lowerName = $activeTheme->getLowerName();
                    $this->cachedThemes[$lowerName] = $activeTheme;
                }
            } catch (\Exception $e) {
                // Log error but don't break the repository functionality
                if (function_exists('error_log')) {
                    error_log('Failed to load active theme from registrar: '.$e->getMessage());
                }
            }
        }

        $this->cacheLoaded = true;
    }

    /**
     * Reset the internal cache.
     *
     * This method is called by the ThemeRegistrar when a new theme is registered
     * to ensure synchronization between the registrar and repository.
     */
    public function resetCache(): static
    {
        $this->cacheLoaded = false;
        $this->cachedThemes = [];

        return $this;
    }
}
