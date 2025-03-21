<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Stringable;

/**
 * Represents a file asset and handles its URL generation.
 *
 * This class provides functionality to generate URLs for assets,
 * with support for different asset containers and Vite integration.
 *
 * @implements Stringable
 */
class AssetFile implements Stringable
{
    /**
     * The asset container identifier.
     */
    protected string $assetContainer = 'theme';

    /**
     * Creates a new asset file instance.
     *
     * @param  string  $path  The path to the asset file
     */
    public function __construct(protected string $path) {}

    /**
     * Sets the asset container to use.
     *
     * @param  string  $assetContainer  The container identifier
     */
    public function from(string $assetContainer): static
    {
        $this->assetContainer = $assetContainer;

        return $this;
    }

    /**
     * Converts the asset file to its URL string representation.
     *
     * This method is called when the object is used as a string.
     * It generates the appropriate URL for the asset using the configured container.
     *
     * @return string The generated asset URL
     */
    public function __toString(): string
    {
        try {
            Application::getInstance();
            $assetContainer = app(AssetContainerManager::class)->get($this->assetContainer);

            if ($assetContainer === null) {
                return '';
            }

            return (new ViteManager($assetContainer))->asset($this->path);
        } catch (\Throwable $e) {
            Log::error('Error in AssetFile::__toString', [
                'error' => $e->getMessage(),
                'path' => $this->path,
            ]);

            return '';
        }
    }
}
