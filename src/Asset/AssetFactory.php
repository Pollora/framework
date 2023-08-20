<?php

declare(strict_types=1);

namespace Pollen\Asset;

class AssetFactory
{
    public function add($handle, $file)
    {
        return new Asset($handle, $file);
    }
}
