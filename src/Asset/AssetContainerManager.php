<?php

declare(strict_types=1);

namespace Pollen\Asset;

use Illuminate\Contracts\Foundation\Application;

class AssetContainerManager
{
    protected Application $app;

    protected array $containers = [];

    protected ?string $defaultContainer = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function addContainer(string $name, array $config): void
    {
        $this->containers[$name] = new AssetContainer($name, $config);
    }

    public function get(string $name): AssetContainer
    {
        if (! isset($this->containers[$name])) {
            throw new \InvalidArgumentException("Asset container [{$name}] not found.");
        }

        return $this->containers[$name];
    }

    public function setDefaultContainer(string $name): void
    {
        $this->defaultContainer = $name;
    }

    public function getDefault(): AssetContainer
    {
        if (! $this->defaultContainer) {
            throw new \RuntimeException('No default asset container has been set.');
        }

        return $this->get($this->defaultContainer);
    }
}
