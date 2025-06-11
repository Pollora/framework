<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Translation\Translator;
use Pollora\Modules\Domain\Contracts\ModuleRepositoryInterface;

/**
 * Generic module bootstrap service inspired by nwidart/laravel-modules.
 * 
 * This service handles the registration and booting of modules.
 */
class ModuleBootstrap
{
    public function __construct(
        protected Container $app,
        protected ModuleRepositoryInterface $repository
    ) {}

    /**
     * Register all enabled modules.
     */
    public function registerModules(): void
    {
        // IMPORTANT: Register autoloading for each module FIRST
        // This must happen before we try to discover and load service providers
        foreach ($this->repository->allEnabled() as $module) {
            if (method_exists($module, 'register')) {
                $module->register();
            }
        }

        // Now that autoloading is set up, we can discover and load service providers
        $manifest = $this->app->make(ModuleManifest::class);
        
        // Register service providers using Laravel's ProviderRepository
        (new ProviderRepository($this->app, new Filesystem, $this->getCachedModulePath()))
            ->load($manifest->getProviders());

        // Register module files
        $manifest->registerFiles();
    }

    /**
     * Boot all enabled modules.
     */
    public function bootModules(): void
    {
        foreach ($this->repository->allEnabled() as $module) {
            if (method_exists($module, 'boot')) {
                $module->boot();
            }
        }
    }

    /**
     * Register module migrations.
     */
    public function registerMigrations(): void
    {
        if (!$this->app['config']->get('modules.auto-discover.migrations', true)) {
            return;
        }

        $this->app->resolving(Migrator::class, function (Migrator $migrator) {
            foreach ($this->repository->allEnabled() as $module) {
                if (method_exists($module, 'getMigrationsPath')) {
                    $migrationsPath = $module->getMigrationsPath();
                    if (is_dir($migrationsPath)) {
                        $migrator->path($migrationsPath);
                    }
                }
            }
        });
    }

    /**
     * Register module translations.
     */
    public function registerTranslations(): void
    {
        if (!$this->app['config']->get('modules.auto-discover.translations', true)) {
            return;
        }

        $this->app->afterResolving('translator', function ($translator) {
            if (!$translator instanceof Translator) {
                return;
            }

            foreach ($this->repository->allEnabled() as $module) {
                $langPath = $module->getPath() . '/languages';
                if (is_dir($langPath)) {
                    $translator->addNamespace($module->getLowerName(), $langPath);
                    $translator->addJsonPath($langPath);
                }
            }
        });
    }

    /**
     * Get the cached module path.
     */
    protected function getCachedModulePath(): string
    {
        return str_replace('services.php', 'modules.php', $this->app->getCachedServicesPath());
    }
}
