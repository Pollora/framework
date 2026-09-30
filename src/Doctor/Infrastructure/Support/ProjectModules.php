<?php

declare(strict_types=1);

namespace Pollora\Doctor\Infrastructure\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;
use Pollora\Plugin\Application\Services\PluginRegistrar;
use Throwable;

/**
 * Everything a project builds and registers: the active theme, the Pollora plugins
 * registered with pollora_register(), and the enabled Laravel modules.
 *
 * Paths come from the asset container when one is set up, and otherwise from the
 * framework's own naming rules (ModuleAssetManager), so a check reads exactly what
 * Pollora will read.
 */
class ProjectModules
{
    public function __construct(private readonly Container $app) {}

    /**
     * @return list<ProjectModule>
     */
    public function all(): array
    {
        return [...$this->theme(), ...$this->plugins(), ...$this->modules()];
    }

    /**
     * @return list<ProjectModule>
     */
    public function theme(): array
    {
        $directory = ActiveTheme::directory();
        $slug = ActiveTheme::slug();

        if ($directory === null || $slug === null || ! is_dir($directory)) {
            return [];
        }

        return [$this->make('theme', $slug, $directory, strtolower($slug), 'theme')];
    }

    /**
     * @return list<ProjectModule>
     */
    public function plugins(): array
    {
        if (! $this->app->bound(PluginRegistrar::class)) {
            return [];
        }

        $plugins = [];

        foreach ($this->app->make(PluginRegistrar::class)->getRegisteredPlugins() as $plugin) {
            $plugins[] = $this->make('plugin', $plugin->getLowerName(), rtrim($plugin->getPath(), '/'), $plugin->getLowerName(), 'plugin');
        }

        return $plugins;
    }

    /**
     * @return list<ProjectModule>
     */
    public function modules(): array
    {
        if (! $this->app->bound('modules')) {
            return [];
        }

        $modules = [];

        try {
            foreach ($this->app->make('modules')->allEnabled() as $module) {
                $slug = Str::kebab((string) $module->getName());
                $modules[] = $this->make('module', (string) $module->getName(), rtrim((string) $module->getPath(), '/'), $slug, 'module');
            }
        } catch (Throwable) {
            return [];
        }

        return $modules;
    }

    private function make(string $type, string $name, string $root, string $assetName, string $assetType): ProjectModule
    {
        $expected = $this->app->make(ModuleAssetManager::class)->expectedAssetConfiguration($assetName, $assetType);
        $container = $this->app->bound(AssetManager::class) ? $this->app->make(AssetManager::class)->getContainer($expected['container']) : null;

        return new ProjectModule(
            type: $type,
            name: $name,
            root: $root,
            hotFile: $container?->getHotFile() ?? $expected['hot_file'],
            buildDirectory: $container?->getBuildDirectory() ?? $expected['build_directory'],
            manifestPath: $container?->getManifestPath() ?? $expected['manifest_path'],
        );
    }
}
