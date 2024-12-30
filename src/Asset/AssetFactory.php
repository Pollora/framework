<?php

declare(strict_types=1);

namespace Pollora\Asset;

/**
 * Factory class for creating Asset instances.
 *
 * Provides methods to create new Asset instances and generate asset URLs
 * through a fluent interface.
 */
class AssetFactory
{
    /**
     * Creates a new Asset instance.
     *
     * @param string $handle Unique identifier for the asset
     * @param string $file   Path to the asset file
     * @return Asset The created Asset instance
     */
    public function add(string $handle, string $file): Asset
    {
        return new Asset($handle, $file);
    }

    /**
     * Creates a new AssetFile instance for URL generation.
     *
     * @param string $file Path to the asset file
     * @return AssetFile The created AssetFile instance
     */
    public function url(string $file): AssetFile
    {
        return new AssetFile($file);
    }
}
