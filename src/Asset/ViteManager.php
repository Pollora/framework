<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use InvalidArgumentException;

class ViteManager
{
    private ?Vite $vite = null;

    public function __construct(
        private readonly AssetContainer $container
    ) {
        $this->initializeVite();
    }

    public function container(): AssetContainer
    {
        return $this->container;
    }

    public function getAssetUrls(array $entrypoints): array
    {
        return $this->getViteInstance()->getAssetUrls($entrypoints);
    }

    public function asset(string $path): string
    {
        return $this->getViteInstance()->asset($path);
    }

    public function isRunningHot(): bool
    {
        return $this->getViteInstance()->isRunningHot();
    }

    public function getViteClientHtml(): string
    {
        return ViteFacade::toHtml();
    }

    private function initializeVite(): void
    {
        $this->vite = ViteFacade::useHotFile($this->container->getHotFile())
            ->useBuildDirectory($this->container->getBuildDirectory())
            ->useManifestFilename($this->container->getManifestPath());
    }

    private function getViteInstance(): Vite
    {
        if (! $this->vite instanceof \Illuminate\Foundation\Vite) {
            $this->initializeVite();
        }

        return $this->vite;
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
                    // Ajouter le fichier JavaScript principal
                    $assets['js'][] = $this->assetPath("{$buildDirectory}/{$chunk['file']}");

                    // Ajouter les fichiers CSS associés
                    foreach ($chunk['css'] ?? [] as $css) {
                        $assets['css'][] = $this->assetPath("{$buildDirectory}/{$css}");
                    }

                    return $assets;
                }, ['js' => [], 'css' => []]);
            return collect($assets)->map(fn ($paths): array => array_unique($paths))->all();
        });
    }
}
