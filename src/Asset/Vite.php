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

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function setClient(string $path): void
    {
        $this->theme = $this->getThemeFromAssetPath($path);
        if ($this->theme) {
            $this->setBuildDirectory("build/{$this->theme}");
        }
        $this->client = $this->app->get(\Illuminate\Foundation\Vite::class)([], $this->theme ? "build/{$this->theme}" : 'build');
    }

    public function viteClientHtml(string $hook): HtmlString
    {
        $this->loadInHook($hook);
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

    public function lookupAssetInManifest(string $path): string
    {
        $manifest = $this->manifest($this->buildDirectory);

        if (isset($manifest[$path])) {
            $path = home_url() . "/{$this->buildDirectory}/{$manifest[$path]['file']}";
        }

        return $path;
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

    public function manifest(string $buildDirectory): array
    {
        $path = $this->manifestPath($buildDirectory);

        if (!isset(static::$manifests[$path])) {
            if (!file_exists($path)) {
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

    public function hotAsset(string $asset): string
    {
        return rtrim(file_get_contents(\Illuminate\Support\Facades\Vite::hotFile())) . "/{$asset}";
    }
}
