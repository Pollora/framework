<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Services;

use Pollora\Asset\Application\Services\AssetManager;

class RootAssetManager
{
    public function __construct(
        protected AssetManager $assetManager
    ) {}

    public function registerRootAssets(): void
    {
        $assetConfig = [
            'hot_file' => public_path('hot'),
            'build_directory' => 'build',
            'manifest_path' => 'manifest.json',
            'base_path' => '',
        ];

        $this->assetManager->addContainer('root', $assetConfig);
        $this->assetManager->setDefaultContainer('root');
    }
}