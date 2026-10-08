<?php

declare(strict_types=1);

namespace Pollora\Modules\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Pollora\Asset\Application\Services\AssetManager;
use Throwable;

/**
 * Give every enabled Laravel module with a Vite build its asset container.
 *
 * A module builds into public/build/module/<kebab> (@pollora/vite-config,
 * type "module"), and `Asset::add(...)->container('module.<kebab>')` reads it
 * from there. The container used to exist only for modules with blocks, or
 * when the module declared it in a provider of its own.
 */
class LaravelModuleAssets
{
    /**
     * Names Vite reads its configuration from.
     *
     * @var list<string>
     */
    private const array VITE_CONFIGS = ['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts', 'vite.config.cjs', 'vite.config.cts'];

    public function __construct(
        private readonly Container $app,
        private readonly ModuleAssetManager $moduleAssets,
    ) {}

    public function setUp(): void
    {
        if (! $this->app->bound('modules') || ! $this->app->bound(AssetManager::class)) {
            return;
        }

        try {
            $modules = $this->app->make('modules')->allEnabled();
        } catch (Throwable) {
            return;
        }

        $assetManager = $this->app->make(AssetManager::class);

        foreach ($modules as $module) {
            $root = rtrim((string) $module->getPath(), '/');
            $slug = Str::kebab((string) $module->getName());

            if ($assetManager->getContainer('module.'.$slug) !== null || ! $this->hasViteBuild($root)) {
                continue;
            }

            $this->moduleAssets->setupModuleAssetContainer($slug, $root, 'module', $slug);
        }
    }

    private function hasViteBuild(string $root): bool
    {
        foreach (self::VITE_CONFIGS as $viteConfig) {
            if (is_file($root.'/'.$viteConfig)) {
                return true;
            }
        }

        return false;
    }
}
