<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Manages multiple asset containers in the application.
 *
 * This class handles the registration and retrieval of asset containers,
 * including management of the default container and container configuration.
 */
class AssetContainerManager
{
    /**
     * Array of registered asset containers.
     *
     * @var array<string, AssetContainer>
     */
    protected array $containers = [];

    /**
     * The name of the default container.
     */
    protected ?string $defaultContainer = null;

    /**
     * Creates a new asset container manager instance.
     *
     * @param  Application  $app  The application container instance
     */
    public function __construct(protected Application $app) {}

    /**
     * Adds a new asset container.
     *
     * @param  string  $name  The unique identifier for the container
     * @param  array  $config  Configuration options for the container
     */
    public function addContainer(string $name, array $config): void
    {
        $this->containers[$name] = new AssetContainer($name, $config);
    }

    /**
     * Gets an asset container by name.
     *
     * @param  string  $name  The container identifier
     * @return AssetContainer|null The requested container
     */
    public function get(string $name): ?AssetContainer
    {
        if (! isset($this->containers[$name])) {
            return null;
        }

        return $this->containers[$name];
    }

    /**
     * Sets the default asset container.
     *
     * @param  string  $name  The container identifier to set as default
     */
    public function setDefaultContainer(string $name): void
    {
        $this->defaultContainer = $name;
    }

    /**
     * Gets the default asset container.
     *
     * @return AssetContainer The default container
     *
     * @throws RuntimeException When no default container is set
     */
    public function getDefault(): AssetContainer
    {
        if ($this->defaultContainer === null || $this->defaultContainer === '' || $this->defaultContainer === '0') {
            throw new RuntimeException('No default asset container has been set.');
        }

        return $this->get($this->defaultContainer);
    }
}
