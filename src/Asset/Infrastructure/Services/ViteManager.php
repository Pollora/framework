<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Services;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Pollora\Asset\Application\Services\MissingBuilds;
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
    public $buildDirectory;

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
     * Without a build, there are none: see isMissingBuild().
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

        if ($this->isMissingBuild()) {
            return ['js' => [], 'css' => []];
        }

        $basePath = $this->container()->getBasePath();

        // If basePath is empty or null, no additional processing is needed
        if ($basePath === '' || $basePath === '0') {
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
     * Without a build, an empty string: see isMissingBuild().
     *
     * @param  string  $path  The asset path
     * @return string The complete asset URL
     */
    public function asset(string $path): string
    {
        if ($this->isMissingBuild()) {
            return '';
        }

        return $this->getViteInstance()->asset($this->container()->getBasePath().$path);
    }

    /**
     * Whether the container has neither a running dev server nor a manifest.
     *
     * Laravel's Vite throws on a missing manifest, and assets are resolved
     * while WordPress boots (blocks register on `init`): one unbuilt theme
     * used to answer 500 everywhere, wp-admin and wp-login.php included
     * (Pollora/pollora#79). The missing build is recorded instead, logged
     * once and named in wp-admin.
     */
    public function isMissingBuild(): bool
    {
        if ($this->isRunningHot()) {
            return false;
        }

        $manifest = public_path($this->container->getBuildDirectory().'/'.$this->container->getManifestPath());

        if (is_file($manifest)) {
            return false;
        }

        resolve(MissingBuilds::class)->record($this->container->getName(), $manifest);

        return true;
    }

    /**
     * The URL of the Vite client on the dev server, or an empty string when Vite is not running hot.
     */
    public function clientUrl(): string
    {
        return $this->isRunningHot() ? $this->getViteInstance()->asset('@vite/client') : '';
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
        return $this->getViteInstance()->toHtml();
    }

    /**
     * Initializes the Vite instance with the asset container configuration.
     *
     * @return Vite The initialized Vite instance
     */
    private function initializeVite(): Vite
    {
        // Laravel's Vite is a singleton: configuring it in place would switch the hot file
        // and build directory of every other container (theme, plugins, blocks) to this one
        $this->vite = (clone ViteFacade::getFacadeRoot())
            ->useHotFile($this->container->getHotFile())
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
        // Bound to the Vite instance it is called on: the build directory must be that
        // instance's, not the one of whichever manager registered the macro last
        ViteFacade::macro('getAssetUrls', function (array $entrypoints) {

            $buildDirectory = $this->buildDirectory;

            $manifest = $this->manifest($buildDirectory);

            $assets = collect($entrypoints)
                ->map(fn ($entrypoint) => $manifest[$entrypoint] ?? null)
                ->filter()
                ->reduce(function (array $assets, array $chunk) use ($buildDirectory): array {

                    $file = $chunk['file'];
                    $filePath = $this->assetPath(sprintf('%s/%s', $buildDirectory, $file));

                    // Determine file type based on extension
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    if ($extension === 'css') {
                        $assets['css'][] = $filePath;
                    } else {
                        $assets['js'][] = $filePath;
                    }

                    // Add additional CSS files
                    foreach ($chunk['css'] ?? [] as $css) {
                        $assets['css'][] = $this->assetPath(sprintf('%s/%s', $buildDirectory, $css));
                    }

                    return $assets;
                }, ['js' => [], 'css' => []]);

            return collect($assets)->map(fn ($paths): array => array_unique($paths))->all();
        });
    }
}
