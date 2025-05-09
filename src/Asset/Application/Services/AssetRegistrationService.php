<?php

namespace Pollora\Asset\Application\Services;

use Pollora\Asset\Domain\Models\Asset;
use Pollora\Asset\Domain\Services\AssetContainerManager;

/**
 * Application service for registering assets into asset containers.
 *
 * This service acts as a use case boundary for asset registration, ensuring
 * that assets are properly instantiated and delegated to the container manager.
 */
class AssetRegistrationService
{
    /**
     * The asset container manager instance.
     *
     * @var AssetContainerManager
     */
    private AssetContainerManager $manager;

    /**
     * Initializes the registration service with the container manager.
     *
     * @param AssetContainerManager $manager The asset container manager
     */
    public function __construct(AssetContainerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Registers a new asset in the container manager.
     *
     * @param string $name The asset name/handle
     * @param string $path The asset file path
     * @param array $attributes Optional attributes (e.g., dependencies, type)
     * @return void
     */
    public function register(string $name, string $path, array $attributes = []): void
    {
        $asset = new Asset($name, $path, $attributes);
        $this->manager->registerAsset($asset);
    }
}
