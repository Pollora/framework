<?php

declare(strict_types=1);

namespace Pollora\Block\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Block\Domain\Contracts\BlockRegistrarInterface;
use Pollora\Modules\Infrastructure\Services\ModuleAssetManager;

/**
 * Registers the blocks of every theme, plugin and module, by convention.
 *
 * Blocks live in `resources/views/blocks` (or the former `resources/blocks`) of
 * the module that ships them; no service provider is needed. This runs on
 * WordPress `init`, hooked while the framework boots — before WordPress loads.
 *
 * A provider of the module's own cannot do this reliably. Over HTTP, WordPress
 * is loaded, and `init` has fired, before theme and plugin providers boot, so a
 * provider hooking `init` from `boot()` is never called; a REST request is
 * answered before those providers boot at all. Blocks registered that way
 * existed in WP-CLI only.
 */
class ModuleBlocksRegistrar
{
    /**
     * Blocks directory, relative to a module root.
     */
    private const string BLOCKS_DIRECTORY = 'resources/views/blocks';

    /**
     * Blocks directory used before v13.32, relative to a module root.
     */
    private const string LEGACY_BLOCKS_DIRECTORY = 'resources/blocks';

    public function __construct(
        private readonly Container $app,
        private readonly BlockRegistrarInterface $registrar,
        private readonly ModuleAssetManager $moduleAssets,
    ) {}

    /**
     * Register the blocks of every theme, plugin and Laravel module that ships some.
     */
    public function registerAll(): void
    {
        $roots = [...$this->moduleAssets->getModuleRoots(), ...$this->laravelModuleRoots()];

        foreach ($roots as $containerName => $root) {
            if ($this->hasBlocks($root)) {
                $this->registrar->registerDirectory($root.'/'.self::BLOCKS_DIRECTORY, $containerName);
            }
        }
    }

    /**
     * Enabled Laravel modules shipping blocks, keyed by asset container name.
     *
     * Themes and plugins get their asset container from the framework before
     * `init`. A Laravel module declares its own, in a provider that boots too
     * late for `init`, so the container is created here when it is missing —
     * the asset container only, never the view paths, which a module registers
     * itself.
     *
     * @return array<string, string>
     */
    private function laravelModuleRoots(): array
    {
        if (! interface_exists(RepositoryInterface::class) || ! $this->app->bound('modules')) {
            return [];
        }

        /** @var RepositoryInterface $modules */
        $modules = $this->app->make('modules');
        $assetManager = $this->app->bound(AssetManager::class) ? $this->app->make(AssetManager::class) : null;
        $roots = [];

        foreach ($modules->allEnabled() as $module) {
            $root = rtrim((string) $module->getPath(), '/');

            if (! $this->hasBlocks($root)) {
                continue;
            }

            $slug = Str::kebab($module->getName());
            $containerName = 'module.'.$slug;

            if (! $assetManager?->getContainer($containerName) && $this->moduleAssets->setupModuleAssetContainer($slug, $root, 'module', $slug) === null) {
                continue;
            }

            $roots[$containerName] = $root;
        }

        return $roots;
    }

    private function hasBlocks(string $root): bool
    {
        return is_dir($root.'/'.self::BLOCKS_DIRECTORY) || is_dir($root.'/'.self::LEGACY_BLOCKS_DIRECTORY);
    }
}
