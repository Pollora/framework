<?php

declare(strict_types=1);

namespace Pollora\Asset;

use Pollora\Foundation\Application;

class AssetFile implements \Stringable
{
    protected string $assetContainer = 'theme';

    protected Application $app;

    public function __construct(protected string $path) {}

    public function from(string $assetContainer): static
    {
        $this->assetContainer = $assetContainer;

        return $this;
    }

    public function __toString(): string
    {
        Application::getInstance();
        $assetContainer = app('asset.container')->get($this->assetContainer);

        if ($assetContainer === null) {
            return ''; // Retourne une chaîne vide si le conteneur n'existe pas
        }

        $viteManager = new ViteManager($assetContainer);

        return $viteManager->asset($this->path) ?? '';
    }
}
