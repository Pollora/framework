<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Illuminate\Contracts\Foundation\Application;

class AssetContainerManager
{
    protected array $containers = [];

    protected ?string $defaultContainer = null;

    public function __construct(protected Application $app)
    {
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
        if ($this->defaultContainer === null || $this->defaultContainer === '' || $this->defaultContainer === '0') {
            throw new \RuntimeException('No default asset container has been set.');
        }

        return $this->get($this->defaultContainer);
    }
}
