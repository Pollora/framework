<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\HtmlString;
use Pollora\Foundation\Application;

class Vite
{
    protected ?HtmlString $client = null;

    protected array $loadedInHooks = [];

    protected string $buildDirectory = 'build';

    protected string $manifestFilename = 'manifest.json';

    protected static array $manifests = [];

    protected ?string $theme = null;

    protected ?string $hotFile = null;

    protected ?AssetContainer $container = null;

    public function __construct(protected Application $app)
    {
    }

    public function retrieveAsset(string $path, string $assetType = '', ?string $assetContainer = null): string
    {
        $container = $assetContainer !== null && $assetContainer !== '' && $assetContainer !== '0' ? app('asset.container')->get($assetContainer) : null;

        if ($container) {
            $assetConfig = $container->getAssetDir();
            $rootDir = $assetConfig['root'];
            $assetTypeDir = $assetConfig[$assetType] ?? '';

            $prefix = $this->buildAssetPrefix($rootDir, $assetTypeDir);
            $path = $prefix . $path;
            //$assetConfig = $this->assetConfig();
        }



        return $this->buildViteAsset($path, $container);
    }

    protected function buildViteAsset(string $path, $container = null): string
    {
        if (!$container) {
            return \Illuminate\Support\Facades\Vite::asset($path);
        }
        return \Illuminate\Support\Facades\Vite::useHotFile($container->getHotFile())
            ->useBuildDirectory($container->getBuildDirectory())
            ->asset($path);
    }

    protected function buildAssetPrefix(string $rootDir, string $assetTypeDir): string
    {
        return $assetTypeDir !== '' && $assetTypeDir !== '0' ? "{$rootDir}/{$assetTypeDir}/" : "{$rootDir}/";
    }

    public function setContainer(AssetContainer $container): void
    {
        $this->container = $container;
        $this->setBuildDirectory($container->getBuildDirectory());
    }

    public function setClient(string $path): void
    {
        $container = $this->container ?? app('asset.container')->getDefault();
        $this->client = $this->app->make(\Illuminate\Foundation\Vite::class)([], $container->getBuildDirectory());
    }

    public function viteClientHtml(string $hook): HtmlString
    {
        $this->loadInHook($hook);

        if (!$this->client instanceof \Illuminate\Support\HtmlString) {
            throw new \RuntimeException('Vite client has not been initialized.');
        }

        return $this->client;
    }

    public function loadInHook(string $hook): void
    {
        $this->loadedInHooks[$hook] = true;
    }

    public function loadedInHook(string $hook): bool
    {
        return isset($this->loadedInHooks[$hook]);
    }

    public function retrieveHotAsset(string $path): string
    {
        return $this->hotAsset($path);
    }

    public function lookupAssetsInManifest(string $path): array
    {
        $manifest = $this->manifest();
        $assets = $this->orderManifest($manifest);

        $paths = ['css' => [], 'js' => []];
        foreach ($assets as $value) {
            $extension = pathinfo((string) $value['file'], PATHINFO_EXTENSION);
            $paths[$extension][] = home_url("/{$this->buildDirectory}/{$value['file']}");

            if (isset($value['css'])) {
                $paths['css'] = array_merge($paths['css'], array_map(
                    fn ($cssPath) => home_url("/{$this->buildDirectory}/{$cssPath}"),
                    $value['css']
                ));
            }
        }

        return $paths;
    }

    public function orderManifest(array $manifest): array
    {
        $entries = array_filter($manifest, fn ($value) => $value['isEntry'] ?? false);
        $ordered = $this->moveLegacyAndPolyfill($entries);

        return array_combine(
            array_map($this->getTokenName(...), array_column($ordered['ordered'], 'file')),
            $ordered['ordered']
        );
    }

    public function getTokenName(string $key): string
    {
        $parts = explode('-', $key);
        $lastPart = end($parts);

        return pathinfo($lastPart, PATHINFO_FILENAME);
    }

    public function moveLegacyAndPolyfill(array $manifest): array
    {
        $categories = [
            'polyfill' => [],
            'legacy' => [],
            'standard' => [],
        ];

        foreach ($manifest as $value) {
            if (str_contains((string) $value['src'], 'polyfills') && str_contains((string) $value['src'], 'legacy')) {
                $categories['polyfill'][] = $value;
            } elseif (str_contains((string) $value['src'], 'legacy')) {
                $categories['legacy'][] = $value;
            } else {
                $categories['standard'][] = $value;
            }
        }

        return [
            'legacy' => $categories['legacy'][0] ?? null,
            'polyfill' => $categories['polyfill'][0] ?? null,
            'cleaned' => $categories['standard'],
            'ordered' => array_merge($categories['standard'], $categories['polyfill'], $categories['legacy']),
        ];
    }

    public function getThemeFromAssetPath(string $path): ?string
    {
        $themePath = config('theme.base_path');

        if (str_starts_with($path, (string) $themePath)) {
            $themePathOffset = strlen((string) $themePath) + 1;
            $truncatedPath = substr($path, $themePathOffset);

            return strtok($truncatedPath, '/');
        }

        return null;
    }

    public function setBuildDirectory(string $buildDirectory): void
    {
        $this->buildDirectory = $buildDirectory;
    }

    public function manifest(): array
    {
        $path = $this->container instanceof \Pollora\Asset\AssetContainer ? $this->container->getManifestPath() : $this->manifestPath($this->buildDirectory);

        if (! isset(static::$manifests[$path])) {
            if (! file_exists($path)) {
                throw new ViteManifestNotFoundException("Vite manifest not found at: $path");
            }

            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }

        return static::$manifests[$path];
    }

    protected function manifestPath(string $buildDirectory): string
    {
        return public_path("{$buildDirectory}/{$this->manifestFilename}");
    }

    public function removeThemesPath($path)
    {
        // Vérifier si le chemin contient "themes/"
        if (str_starts_with((string) $path, 'themes/')) {
            // Découper le chemin par les slashes
            $parts = explode('/', (string) $path);

            // Supprimer les deux premières parties (themes/ et le nom du thème)
            $parts = array_slice($parts, 2);

            // Reconstituer le chemin sans les deux premières parties
            return implode('/', $parts);
        }

        // Si "themes/" n'est pas trouvé, retourner le chemin original
        return $path;
    }

    public function hotAsset(string $asset): string
    {
        $asset = $this->removeThemesPath($asset);

        return rtrim(file_get_contents(\Illuminate\Support\Facades\Vite::hotFile()))."/{$asset}";
    }
}
