<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use InvalidArgumentException;

class ViteManager
{
    private const ASSET_TYPES = [
        'image' => 'images',
        'font' => 'fonts',
        'css' => 'css',
        'js' => 'js',
    ];

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
        if (! $this->vite) {
            $this->initializeVite();
        }

        return $this->vite;
    }

    public function registerMacros(): void
    {
        $this->registerAssetUrlsMacro();
        $this->registerAssetTypesMacros();
    }

    private function registerAssetUrlsMacro(): void
    {
        $viteManager = $this;
        ViteFacade::macro('getAssetUrls', function (array $entrypoints) use ($viteManager) {
            $buildDirectory = $viteManager->container()->getBuildDirectory();
            $manifest = $this->manifest($buildDirectory);

            // Utiliser les collections pour collecter les assets
            $assets = collect($entrypoints)
                ->map(fn ($entrypoint) => $manifest[$entrypoint] ?? null)
                ->filter()
                ->reduce(function ($assets, $chunk) use ($buildDirectory) {
                    // Ajouter le fichier JavaScript principal
                    $assets['js'][] = $this->assetPath("{$buildDirectory}/{$chunk['file']}");

                    // Ajouter les fichiers CSS associés
                    foreach ($chunk['css'] ?? [] as $css) {
                        $assets['css'][] = $this->assetPath("{$buildDirectory}/{$css}");
                    }

                    return $assets;
                }, ['js' => [], 'css' => []]);

            // Supprimer les doublons et retourner les assets triés par type
            return collect($assets)->map(fn ($paths) => array_unique($paths))->all();
        });
    }

    private function registerAssetTypesMacros(): void
    {
        foreach (self::ASSET_TYPES as $macroName => $assetType) {
            $this->registerAssetTypeMacro($macroName, $assetType);
        }
    }

    private function registerAssetTypeMacro(string $macroName, string $assetType): void
    {
        $viteManager = $this;
        ViteFacade::macro($macroName, function (string $path) use ($viteManager, $assetType) {
            return $viteManager->retrieveAsset($path, $assetType);
        });
    }

    public function retrieveAsset(string $path, string $assetType): string
    {
        $this->validateAssetType($assetType);

        $assetConfig = $this->container->getAssetDir();
        $prefix = $this->buildAssetPrefix(
            $assetConfig['root'],
            $assetConfig[$assetType] ?? ''
        );

        return $this->asset($prefix.$path);
    }

    private function validateAssetType(string $assetType): void
    {
        if (! in_array($assetType, self::ASSET_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid asset type: %s. Allowed types are: %s',
                    $assetType,
                    implode(', ', self::ASSET_TYPES)
                )
            );
        }
    }

    private function buildAssetPrefix(string $rootDir, string $assetTypeDir): string
    {
        return $assetTypeDir ? "{$rootDir}/{$assetTypeDir}/" : "{$rootDir}/";
    }
}
