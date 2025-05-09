<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Domain\Contracts\AssetFileInterface;
use Pollora\Asset\Domain\Models\AssetFile as DomainAssetFile;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;

/**
 * Infrastructure implementation for asset file URL resolution (Vite, containers, etc.).
 *
 * This class extends the domain AssetFile and implements the AssetFileInterface contract.
 * It resolves the public URL of an asset using the configured container and Vite integration.
 */
class AssetFile extends DomainAssetFile implements AssetFileInterface
{
    /**
     * Converts the asset file to its URL string representation using infrastructure logic.
     *
     * @return string The generated asset URL
     */
    public function __toString(): string
    {
        try {
            Application::getInstance();
            /** @var AssetContainer|null $assetContainer */
            $assetContainer = app(AssetManager::class)->getContainer($this->assetContainer);
            if ($assetContainer === null) {
                return '';
            }
            return (new ViteManager($assetContainer))->asset($this->filename);
        } catch (\Throwable $e) {
            Log::error('Error in AssetFile::__toString', [
                'error' => $e->getMessage(),
                'path' => $this->filename,
            ]);
            return '';
        }
    }
}
