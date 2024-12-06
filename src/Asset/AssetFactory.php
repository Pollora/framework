<?php

declare(strict_types=1);

namespace Pollora\Asset;

class AssetFactory
{
    public function add(string $handle, string $file): \Pollora\Asset\Asset
    {
        return new Asset($handle, $file);
    }

    public function url(string $handle): AssetFile
    {
        return new AssetFile($handle);
    }
}
