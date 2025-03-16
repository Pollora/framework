<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;

/**
 * Manages Vite integration for asset handling in the application.
 *
 * This class provides a wrapper around Laravel's Vite implementation,
 * handling asset compilation, hot module replacement, and asset URL generation.
 *
 * @property-read AssetContainer $container The asset container instance
 * @property-read ?Vite $vite The Vite instance
 */
class ViteManager
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
    public function getAssetUrls(array $entrypoints): array
    {
        if ($entrypoints === []) {
            throw new AssetException('Entry points array cannot be empty.');
        }

        return $this->getViteInstance()->getAssetUrls($entrypoints);
    }

    /**
     * Gets the URL for a specific asset path.
     *
     * @TODO rework with the Asset facade
     *
     * @param  string  $path  The asset path
     * @return string The complete asset URL
     */
    public function asset(string $path): string
    {
        return $this->getViteInstance()->asset($path);
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

    private function initializeVite(): Vite
    {
        $this->vite = ViteFacade::useHotFile($this->container->getHotFile())
            ->useBuildDirectory($this->container->getBuildDirectory())
            ->useManifestFilename($this->container->getManifestPath());

        return $this->vite;
    }

    private function getViteInstance(): Vite
    {
        return $this->vite ?? $this->initializeVite();
    }

    public function registerMacros(): void
    {
        $this->registerAssetUrlsMacro();
    }

    private function registerAssetUrlsMacro(): void
    {
        $viteManager = $this;
        ViteFacade::macro('getAssetUrls', function (array $entrypoints) use ($viteManager) {
            $buildDirectory = $viteManager->container()->getBuildDirectory();
            $manifest = $this->manifest($buildDirectory);
            $assets = collect($entrypoints)
                ->map(fn ($entrypoint) => $manifest[$entrypoint] ?? null)
                ->filter()
                ->reduce(function (array $assets, $chunk) use ($buildDirectory) {
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
