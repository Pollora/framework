<?php

declare(strict_types=1);

namespace Pollora\Asset\Domain\Models;

/**
 * Domain model representing an asset (CSS, JS, image, etc.).
 *
 * Encapsulates the asset's name, file path, and additional attributes (such as type, version, dependencies, etc.).
 * Used throughout the domain and application layers to represent a single asset entity.
 */
class Asset
{
    /**
     * The unique name or handle of the asset.
     */
    private string $name;

    /**
     * The file path or URL to the asset.
     */
    private string $path;

    /**
     * Additional attributes for the asset (type, version, dependencies, etc.).
     */
    private array $attributes;

    /**
     * Initializes a new asset instance.
     *
     * @param  string  $name  The unique name or handle of the asset
     * @param  string  $path  The file path or URL to the asset
     * @param  array  $attributes  Additional attributes (optional)
     */
    public function __construct(string $name, string $path, array $attributes = [])
    {
        $this->name = $name;
        $this->path = $path;
        $this->attributes = $attributes;
    }

    /**
     * Gets the asset's unique name or handle.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the file path or URL to the asset.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the asset's additional attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
