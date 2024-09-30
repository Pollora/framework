<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\HtmlString;
use Pollen\Foundation\Application;

class Vite
{
    protected ?HtmlString $client = null;

    protected array $loadedInHooks = [];

    protected string $buildDirectory = 'build';

    protected string $manifestFilename = 'manifest.json';

    protected static array $manifests = [];

    protected ?string $theme = null;

    protected Application $app;

    protected ?string $hotFile = null;

    protected ?AssetContainer $container = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
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

        if ($this->client === null) {
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
        $manifest = $this->manifest($this->buildDirectory);
        $assets = $this->orderManifest($manifest);

        $paths = ['css' => [], 'js' => []];
        foreach ($assets as $value) {
            $extension = pathinfo($value['file'], PATHINFO_EXTENSION);
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
            array_map([$this, 'getTokenName'], array_column($ordered['ordered'], 'file')),
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
            if (str_contains($value['src'], 'polyfills') && str_contains($value['src'], 'legacy')) {
                $categories['polyfill'][] = $value;
            } elseif (str_contains($value['src'], 'legacy')) {
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

        if (str_starts_with($path, $themePath)) {
            $themePathOffset = strlen($themePath) + 1;
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
        $path = $this->container ? $this->container->getManifestPath() : $this->manifestPath($this->buildDirectory);

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
        if (strpos($path, 'themes/') === 0) {
            // Découper le chemin par les slashes
            $parts = explode('/', $path);

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
