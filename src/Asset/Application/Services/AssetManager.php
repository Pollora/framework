<?php

declare(strict_types=1);

namespace Pollora\Asset\Application\Services;

use Pollora\Asset\Domain\Models\Asset;
use Pollora\Asset\Infrastructure\Repositories\AssetContainer;
use Pollora\Asset\Infrastructure\Services\AssetEnqueuer;
use Pollora\Asset\Infrastructure\Services\AssetFile;

/**
 * Application service for managing asset containers and asset file resolution.
 *
 * This class provides methods to add, retrieve, and manage asset containers,
 * as well as to generate asset URLs through the infrastructure layer (Vite, manifest, etc).
 */
class AssetManager
{
    /**
     * List of registered asset containers.
     *
     * @var array<string, AssetContainer>
     */
    protected array $containers = [];

    /**
     * The default asset container name.
     */
    protected ?string $defaultContainer = null;

    /**
     * Initializes the asset manager with the registration and retrieval services.
     *
     * @param  AssetRegistrationService  $registrationService  Service for registering assets
     * @param  AssetRetrievalService  $retrievalService  Service for retrieving assets
     */
    public function __construct(AssetRegistrationService $registrationService, private readonly AssetRetrievalService $retrievalService) {}

    /**
     * Factory method: returns an AssetEnqueuer (builder) for fluent asset management and enqueueing.
     *
     * @param  string  $handle  Asset handle
     * @param  string  $file  Asset file path
     * @return AssetEnqueuer Asset enqueuer instance
     */
    public function add(string $handle, string $file): AssetEnqueuer
    {
        return app(\Pollora\Asset\Infrastructure\Services\AssetEnqueuer::class)
            ->handle($handle)
            ->path($file);
    }

    /**
     * Retrieves an asset by name.
     *
     * @param  string  $name  Asset name
     * @return Asset|null Asset instance or null if not found
     */
    public function get(string $name): ?Asset
    {
        return $this->retrievalService->get($name);
    }

    /**
     * Retrieves all registered assets.
     *
     * @return array List of asset instances
     */
    public function all(): array
    {
        return $this->retrievalService->all();
    }

    /**
     * Adds a new asset container.
     *
     * @param  string  $name  Name of the container
     * @param  array  $config  Configuration for the container
     */
    public function addContainer(string $name, array $config): void
    {
        $this->containers[$name] = new AssetContainer($name, $config);
    }

    /**
     * Retrieves an asset container by name.
     *
     * @param  string  $name  Name of the container
     * @return AssetContainer|null The asset container instance or null if not found
     */
    public function getContainer(string $name): ?AssetContainer
    {
        return $this->containers[$name] ?? null;
    }

    /**
     * Sets the default asset container.
     *
     * @param  string  $name  Name of the container to set as default
     */
    public function setDefaultContainer(string $name): void
    {
        $this->defaultContainer = $name;
    }

    /**
     * Gets the default asset container instance.
     *
     * @return AssetContainer|null The default asset container instance or null if not set
     */
    public function getDefaultContainer(): ?AssetContainer
    {
        return $this->defaultContainer !== null && $this->defaultContainer !== '' && $this->defaultContainer !== '0' ? $this->getContainer($this->defaultContainer) : null;
    }

    /**
     * Infrastructure factory for asset file URL resolution (Vite, containers, etc.).
     *
     * @param  string  $file  Asset file path
     * @return AssetFile Asset file instance for URL resolution
     */
    public function url(string $file): AssetFile
    {
        return new AssetFile($file);
    }
}
