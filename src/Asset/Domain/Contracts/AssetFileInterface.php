<?php

namespace Pollora\Asset\Domain\Contracts;

/**
 * Contract for asset file value objects.
 *
 * This interface defines the required methods for asset file representations
 * in the domain and infrastructure layers.
 */
interface AssetFileInterface
{
    /**
     * Gets the asset file name or path.
     * @return string
     */
    public function getFilename(): string;

    /**
     * Gets the asset container identifier.
     * @return string
     */
    public function getAssetContainer(): string;

    /**
     * Sets the asset container.
     * @param string $container
     * @return static
     */
    public function from(string $container): static;

    /**
     * String representation (for URL or path).
     * @return string
     */
    public function __toString(): string;
}
