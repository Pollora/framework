<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Services;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Pollora\Asset\Domain\Contracts\ViteManagerInterface;
use Pollora\Asset\Domain\Exceptions\AssetException;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

/**
 * Infrastructure implementation of ViteManagerInterface using Vite and asset containers.
 *
 * Provides asset URL resolution and hot-reload detection using Vite integration.
 *
 * This class provides a wrapper around Laravel's Vite implementation,
 * handling asset compilation, hot module replacement, and asset URL generation.
 *
 * @property-read AssetContainer $container The asset container instance
 * @property-read ?Vite $vite The Vite instance
 */
class ViteManager implements ViteManagerInterface
{
    /**
     * The Vite instance.
     */
    private ?Vite $vite = null;

    /**
     * Create a new ViteManager instance.
     *
     * @param  AssetContainer  $container  The asset container to use
     */
    public function __construct(
        private readonly AssetContainer $container
    ) {
        $this->initializeVite();
        $this->registerMacros();
    }

    /**
     * Returns the asset container instance.
     */
    public function container(): AssetContainer
    {
        return $this->container;
    }

    /**
     * Gets the URLs for the specified entry points.
     *
     * @param  array  $entrypoints  List of entry points to process
     * @return array Array of asset URLs grouped by type (js/css)
     *
     * @throws AssetException When entrypoints array is empty
     */
    /**
     * Gets the URLs for the specified entry points.
     *
     * @param  array  $entrypoints  List of entry points to process
     * @return array Array of asset URLs grouped by type (js/css)
     *
     * @throws AssetException When entrypoints array is empty
     */
    public function getAssetUrls(array $entrypoints): array
    {
        if (empty($entrypoints)) {
            throw new AssetException('Entry points array cannot be empty.');
        }

        $basePath = $this->container()->getBasePath();

        // If basePath is empty or null, no additional processing is needed
        if (empty($basePath)) {
            return $this->getViteInstance()->getAssetUrls($entrypoints);
        }

        // Prefix each entrypoint with the basePath
        $prefixedEntrypoints = array_map(
            fn (string $entrypoint): string => $basePath.ltrim($entrypoint, '/'),
            $entrypoints
        );

        return $this->getViteInstance()->getAssetUrls($prefixedEntrypoints);
    }

    /**
     * Gets the URL for a specific asset path.
     *
     * @param  string  $path  The asset path
     * @return string The complete asset URL
     */
    public function asset(string $path): string
    {
        return $this->getViteInstance()->asset($this->container()->getBasePath().$path);
    }

    /**
     * Checks if Vite is running in hot module replacement mode.
     *
     * @return bool True if HMR is active, false otherwise
     */
    public function isRunningHot(): bool
    {
        return $this->getViteInstance()->isRunningHot();
    }

    /**
     * Gets the Vite client HTML script tag.
     *
     * @return string The HTML script tag for Vite client
     */
    public function getViteClientHtml(): string
    {
        return ViteFacade::toHtml();
    }

    /**
     * Initializes the Vite instance with the asset container configuration.
     *
     * @return Vite The initialized Vite instance
     */
    private function initializeVite(): Vite
    {
        $this->vite = ViteFacade::useHotFile($this->container->getHotFile())
            ->useBuildDirectory($this->container->getBuildDirectory())
            ->useManifestFilename($this->container->getManifestPath());

        return $this->vite;
    }

    /**
     * Retrieves the Vite instance, initializing it if necessary.
     *
     * @return Vite The Vite instance
     */
    private function getViteInstance(): Vite
    {
        return $this->vite ?? $this->initializeVite();
    }

    /**
     * Registers custom macros for the Vite facade.
     *
     * This method allows additional functionality to be added dynamically to Vite.
     */
    public function registerMacros(): void
    {
        $this->registerAssetUrlsMacro();
    }

    /**
     * Registers a macro for retrieving asset URLs from the Vite manifest.
     *
     * This macro allows resolving entry point assets including JavaScript and CSS files.
     */
    private function registerAssetUrlsMacro(): void
    {
        $viteManager = $this;
        ViteFacade::macro('getAssetUrls', function (array $entrypoints) use ($viteManager) {
            /** @var Vite $this */
            $buildDirectory = $viteManager->container()->getBuildDirectory();
            $manifest = $this->manifest($buildDirectory);
            $assets = collect($entrypoints)
                ->map(fn ($entrypoint) => $manifest[$entrypoint] ?? null)
                ->filter()
                ->reduce(function (array $assets, $chunk) use ($buildDirectory) {
                    /** @var Vite $this */
                    $assets['js'][] = $this->assetPath("{$buildDirectory}/{$chunk['file']}");
                    foreach ($chunk['css'] ?? [] as $css) {
                        $assets['css'][] = $this->assetPath("{$buildDirectory}/{$css}");
                    }

                    return $assets;
                }, ['js' => [], 'css' => []]);

            return collect($assets)->map(fn ($paths): array => array_unique($paths))->all();
        });
    }
}
