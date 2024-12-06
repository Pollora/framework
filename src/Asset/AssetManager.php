<?php

declare(strict_types=1);

namespace Pollora\Asset;

class AssetManager
{
    public function __construct(
        private readonly AssetFactory $factory,
        private readonly AssetContainerManager $containerManager
    ) {}

    public function add(string $handle, string $file): Asset
    {
        return $this->factory->add($handle, $file);
    }

    public function container(?string $name = null): AssetContainer
    {
        return $this->containerManager->container($name);
    }
}
